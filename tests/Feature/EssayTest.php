<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\AiQuestionService;
use App\Services\QuizEngine;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EssayTest extends TestCase
{
    use RefreshDatabase;

    public function test_skor_acak_memakai_bobot_dan_essay_dinilai_manual(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay, $benar] = $this->buatAcakBerjalan();

        app(QuizEngine::class)->selesaikanAcak($attempt);

        $attempt->refresh();
        $this->assertSame('selesai', $attempt->status);
        $this->assertSame(1, $attempt->jumlah_benar);
        $this->assertSame(2, $attempt->poin_total);
        $this->assertSame(12, $attempt->poin_maksimal);
        $this->assertSame(17, $attempt->skor_total);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay->id => 8],
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(10, $attempt->poin_total);
        $this->assertSame(12, $attempt->poin_maksimal);
        $this->assertSame(83, $attempt->skor_total);

        $jawabanEssay = Answer::where('attempt_id', $attempt->id)
            ->where('question_id', $essay->id)
            ->first();

        $this->assertNotNull($jawabanEssay->dinilai_at);
        $this->assertSame(8, $jawabanEssay->skor);
        $this->assertSame($admin->id, $jawabanEssay->dinilai_oleh);
        $audit = AuditLog::where('action', 'essay_score_changed')->where('subject_id', $jawabanEssay->id)->firstOrFail();
        $this->assertNull($audit->metadata['from']);
        $this->assertSame(8, $audit->metadata['to']);
        $this->assertSame($admin->id, $audit->actor_id);
    }

    public function test_peserta_bisa_menyimpan_dan_menghapus_jawaban_essay(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => 'Jawaban essay baru.',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => 'Jawaban essay baru.',
            'option_id' => null,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => '   ',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
        ]);
    }

    public function test_mode_serempak_menolak_soal_essay(): void
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-serempak-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Essay',
            'created_by' => $admin->id,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan sesuatu.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Serempak Essay',
            'mode' => 'serempak',
            'status' => 'lobby',
            'display_token' => 'token-serempak-essay',
            'created_by' => $admin->id,
        ]);

        try {
            app(QuizEngine::class)->mulaiSesi($session);
            $this->fail('Mode Serempak seharusnya menolak soal essay.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sesi', $e->errors());
        }

        $this->assertSame('lobby', $session->fresh()->status);
    }

    public function test_kuis_menyimpan_flag_auto_grade_essay(): void
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-auto-flag',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-auto-flag',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Kelas Auto',
            'pin' => 'AUTO01',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-auto-flag'])
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Auto',
            ])
            ->assertRedirect();

        $quiz = Quiz::where('judul', 'Kuis Auto')->firstOrFail();
        $this->assertFalse($quiz->auto_grade_essay);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-auto-flag'])
            ->put(route('admin.quizzes.update', $quiz), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Auto',
                'auto_grade_essay' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($quiz->fresh()->auto_grade_essay);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-auto-flag'])
            ->get(route('admin.quizzes.edit', $quiz))
            ->assertSee('Nilai essay otomatis dengan AI');
    }

    public function test_force_organisasi_memakai_pengaturan_ai_organisasi(): void
    {
        $organization = Organization::query()->firstOrFail();

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_base_url' => 'https://org.example.test/v1',
            'ai_api_key' => 'org-key',
            'ai_default_model' => 'org-model',
        ]);

        config([
            'services.ai.base_url' => 'https://platform.example.test/v1',
            'services.ai.api_key' => 'platform-key',
            'services.ai.default_model' => 'platform-model',
        ]);

        Http::fake([
            'https://org.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 7,
                            'confidence' => 80,
                            'feedback' => 'Cukup.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $context = app(OrganizationContext::class);
        $context->force($organization);

        try {
            $result = app(AiQuestionService::class)
                ->evaluateEssay('Pertanyaan', null, [], 'Jawaban', 10);
        } finally {
            $context->force(null);
        }

        $this->assertSame('org-model', $result['model']);
        Http::assertSent(fn ($request) => $request->url() === 'https://org.example.test/v1/chat/completions');
    }

    public function test_essay_grader_menyimpan_saran_dan_nilai_final(): void
    {
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 90,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $grader = app(\App\Services\EssayGrader::class);

        $suggestion = $grader->suggest($answer);
        $this->assertTrue($grader->applyFinal($answer->fresh(), $suggestion['score'], $admin->id));

        $answer->refresh();
        $this->assertSame(8, $answer->skor);
        $this->assertNotNull($answer->dinilai_at);
        $this->assertSame($admin->id, $answer->dinilai_oleh);
    }

    public function test_admin_dapat_meminta_saran_ai_tanpa_menetapkan_nilai_final(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 91,
                            'feedback' => 'Jawaban menyebut TLS dan perbedaan enkripsi.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);
        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.answers.ai-suggestion', $answer))
            ->assertRedirect();

        $answer->refresh();
        $this->assertSame(8, $answer->ai_suggested_score);
        $this->assertSame(91, $answer->ai_confidence);
        $this->assertSame(0, $answer->skor);
        $this->assertNull($answer->dinilai_at);
        $audit = AuditLog::where('action', 'essay_ai_suggested')->where('subject_id', $answer->id)->firstOrFail();
        $this->assertSame('ai.example.test', $audit->metadata['provider']);
        $this->assertSame('test-model', $audit->metadata['model']);
        $this->assertDatabaseHas('ai_usage_events', [
            'organization_id' => $attempt->organization_id,
            'kind' => 'essay',
            'requests' => 1,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('Riwayat penilaian (1)')
            ->assertSee('ai.example.test');
    }

    /**
     * @return array{0: User, 1: User, 2: QuizSession, 3: Attempt, 4: Question, 5: Question, 6: Option}
     */
    public function test_halaman_mengerjakan_soal_tanpa_sidebar_peserta(): void
    {
        [$admin, $peserta, $session, $attempt] = $this->buatAcakBerjalan();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.show', $attempt))
            ->assertOk()
            ->assertSee('Ibu kota Indonesia?')
            ->assertDontSee('Kelas Saya')
            ->assertDontSee('Portal Peserta');
    }

    private function buatAcakBerjalan(bool $denganJawaban = true): array
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta',
            'username' => 'peserta-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Essay',
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Ibu kota Indonesia?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 2,
            'durasi_detik' => 60,
        ]);

        $benar = Option::create([
            'question_id' => $pg->id,
            'teks' => 'Jakarta',
            'is_correct' => true,
        ]);

        Option::create([
            'question_id' => $pg->id,
            'teks' => 'Bandung',
            'is_correct' => false,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan perbedaan HTTP dan HTTPS.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'HTTPS memakai TLS.',
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Essay',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-acak-essay',
            'created_by' => $admin->id,
        ]);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'berjalan',
            'mulai_at' => now()->getTimestampMs(),
            'batas_akhir_at' => now()->addHour()->getTimestampMs(),
            'bergabung_at' => now(),
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $pg->id,
            'urutan' => 1,
            'urutan_opsi_json' => [$benar->id],
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'urutan' => 2,
            'urutan_opsi_json' => [],
        ]);

        if ($denganJawaban) {
            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $pg->id,
                'option_id' => $benar->id,
                'waktu_jawab_ms' => 1000,
                'skor' => 0,
                'answered_at' => now(),
            ]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $essay->id,
                'jawaban_teks' => 'HTTP tidak terenkripsi, HTTPS memakai TLS.',
                'waktu_jawab_ms' => 2000,
                'skor' => 0,
                'answered_at' => now(),
            ]);
        }

        return [$admin, $peserta, $session, $attempt, $pg, $essay, $benar];
    }
}
