<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\QuestionMedia;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\EssayGrader;
use App\Support\QuestionContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class StructuredEssayContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_structured_blocks_media_and_leaf_answer_format(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin-structured', 'admin', 'admin-token');
        $quiz = Quiz::create(['judul' => 'Kuis Terstruktur', 'created_by' => $admin->id]);

        $this->actingAs($admin)->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Analisis diagram berikut.',
                'tipe' => 'essay',
                'bobot' => 20,
                'content_blocks' => [
                    ['type' => 'text', 'text' => '**Perhatikan** setiap komponen.'],
                    [
                        'type' => 'image',
                        'image' => UploadedFile::fake()->image('diagram.png', 640, 360),
                        'alt' => 'Diagram aliran data',
                        'caption' => 'Diagram utama',
                        'ai_context' => 'Data bergerak dari A ke B.',
                    ],
                    ['type' => 'code', 'language' => 'php', 'code' => '<?php echo $value;'],
                    ['type' => 'table', 'headers' => ['Komponen', 'Fungsi'], 'rows' => [['A', 'Masukan']]],
                ],
                'sub_soal' => [[
                    'key' => 'a',
                    'parent_key' => null,
                    'label' => 'A',
                    'teks' => 'Lengkapi tabel analisis.',
                    'answer_format' => 'table',
                    'answer_config' => ['columns' => ['Temuan', 'Alasan'], 'rows' => ['Komponen A', 'Komponen B']],
                ]],
            ])
            ->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        $question = Question::where('teks', 'Analisis diagram berikut.')->firstOrFail();
        $this->assertCount(4, $question->content_blocks_json);
        $this->assertSame('table', $question->sub_soal_json[0]['answer_format']);
        $this->assertSame(['Temuan', 'Alasan'], $question->sub_soal_json[0]['answer_config']['columns']);
        $media = QuestionMedia::firstOrFail();
        $this->assertSame($question->id, $media->question_id);
        Storage::disk('local')->assertExists($media->path);
        $this->assertArrayNotHasKey('image', $question->content_blocks_json[1]);
        $this->actingAs($admin)->withSession(['auth_token' => 'admin-token'])
            ->get(route('question-media.show', $media))
            ->assertOk();
        $this->actingAs($admin)->withSession(['auth_token' => 'admin-token'])
            ->delete(route('admin.quizzes.questions.destroy', [$quiz, $question]))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseMissing('question_media', ['id' => $media->id]);
    }

    public function test_table_answer_is_saved_structurally_and_reports_completion(): void
    {
        [$participant, $attempt, $question] = $this->runningTableAttempt();
        $url = route('peserta.attempt.answer', $attempt);

        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->postJson($url, [
                'question_id' => $question->id,
                'jawaban_table' => ['cells' => [['TCP', ''], ['UDP', 'Cepat']]],
            ])
            ->assertOk()
            ->assertJsonPath('complete', false);

        $answer = Answer::firstOrFail();
        $this->assertSame('table', $answer->jawaban_data_json['__main']['format']);
        $this->assertStringContainsString('| Baris 1 | TCP |  |', $answer->jawaban_teks);

        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->postJson($url, [
                'question_id' => $question->id,
                'jawaban_table' => ['cells' => [['TCP', 'Andal'], ['UDP', 'Cepat']]],
            ])
            ->assertOk()
            ->assertJsonPath('complete', true);

        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->postJson($url, ['question_id' => $question->id, 'jawaban_teks' => 'bukan tabel'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Soal ini harus dijawab melalui tabel yang tersedia.');
    }

    public function test_question_media_requires_signature_or_authorized_attempt(): void
    {
        Storage::fake('local');
        [$participant, $attempt, $question] = $this->runningTableAttempt();
        $path = 'question-media/'.$question->organization_id.'/diagram.png';
        Storage::disk('local')->put($path, 'png');
        $media = $question->media()->create([
            'organization_id' => $question->organization_id,
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 3,
            'original_name' => 'diagram.png',
        ]);

        $this->get(route('question-media.show', $media))->assertForbidden();
        $this->get(URL::temporarySignedRoute('question-media.show', now()->addMinute(), ['media' => $media->id]))->assertOk();
        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->get(route('question-media.show', $media))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $outsider = $this->user('participant-outsider', 'peserta', 'outsider-token');
        $this->actingAs($outsider)->withSession(['auth_token' => 'outsider-token'])
            ->get(route('question-media.show', $media))
            ->assertForbidden();
    }

    public function test_nested_leaf_parts_accept_their_own_answer_formats_only(): void
    {
        [$participant, $attempt, $question] = $this->runningTableAttempt();
        $question->update(['sub_soal_json' => [
            ['key' => 'a', 'parent_key' => null, 'label' => 'A', 'teks' => 'Tulis fungsi.', 'answer_format' => 'code', 'answer_config' => ['language' => 'php']],
            ['key' => 'c', 'parent_key' => null, 'label' => 'C', 'teks' => 'Analisis protokol.'],
            ['key' => 'c1', 'parent_key' => 'c', 'label' => '1', 'teks' => 'Isi tabel.', 'answer_format' => 'table', 'answer_config' => ['columns' => ['Protokol'], 'rows' => ['Pertama']]],
        ]]);
        $url = route('peserta.attempt.answer', $attempt);

        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->postJson($url, ['question_id' => $question->id, 'part_key' => 'a', 'jawaban_teks' => '<?php return true;'])
            ->assertOk();
        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->postJson($url, ['question_id' => $question->id, 'part_key' => 'c', 'jawaban_teks' => 'induk'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bagian soal tidak dikenali.');
        $this->actingAs($participant)->withSession(['auth_token' => 'participant-token'])
            ->postJson($url, ['question_id' => $question->id, 'part_key' => 'c1', 'jawaban_table' => ['cells' => [['TCP']]]])
            ->assertOk()
            ->assertJsonPath('complete', true);

        $answer = Answer::firstOrFail();
        $this->assertSame('code', $answer->jawaban_data_json['a']['format']);
        $this->assertSame('table', $answer->jawaban_data_json['c1']['format']);
        $this->assertStringContainsString("A:\n<?php return true;", $answer->jawaban_teks);
        $this->assertStringContainsString("C.1:\n", $answer->jawaban_teks);
        $this->assertStringContainsString('```php', QuestionContent::answerForGrading($question->fresh(), $answer));
    }

    public function test_markdown_and_code_are_rendered_without_executable_html(): void
    {
        $html = QuestionContent::renderBlocks([
            ['type' => 'text', 'text' => '<script>alert(1)</script> **aman** ![remote](https://example.com/a.png)'],
            ['type' => 'code', 'language' => 'html', 'code' => '<script>alert(2)</script>'],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<strong>aman</strong>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
    }

    public function test_ai_grading_requires_context_for_question_images(): void
    {
        [, $attempt, $question] = $this->runningTableAttempt();
        $question->update(['content_blocks_json' => [[
            'type' => 'image',
            'media_id' => 999,
            'alt' => 'Diagram tanpa konteks',
            'caption' => '',
            'ai_context' => '',
        ]]]);
        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'jawaban_teks' => 'Jawaban terisi.',
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tambahkan konteks gambar untuk AI');
        app(EssayGrader::class)->suggest($answer);
    }

    private function runningTableAttempt(): array
    {
        $admin = $this->user('admin-table', 'admin', 'admin-table-token');
        $participant = $this->user('participant-table', 'peserta', 'participant-token');
        $quiz = Quiz::create(['judul' => 'Kuis Tabel', 'created_by' => $admin->id]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Bandingkan protokol.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 120,
            'answer_format' => 'table',
            'answer_config_json' => ['columns' => ['Protokol', 'Karakteristik'], 'rows' => ['Baris 1', 'Baris 2']],
        ]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Tabel',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'structured-table-token',
            'created_by' => $admin->id,
        ]);
        $attempt = Attempt::create([
            'user_id' => $participant->id,
            'quiz_session_id' => $session->id,
            'status' => 'berjalan',
            'mulai_at' => now()->getTimestampMs(),
            'batas_akhir_at' => now()->addHour()->getTimestampMs(),
            'bergabung_at' => now(),
        ]);
        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'urutan' => 1,
            'urutan_opsi_json' => [],
        ]);

        return [$participant, $attempt, $question];
    }

    private function user(string $username, string $role, string $token): User
    {
        return User::create([
            'nama' => $username,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => $role,
            'current_session_token' => $token,
        ]);
    }
}
