<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionMedia;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuizDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_menduplikasi_kuis_terkunci_beserta_opsi_rubrik_subsoal_dan_media(): void
    {
        Storage::fake('local');

        [$organization, $admin] = $this->buatTenant('kampus-duplikat', 'admin-duplikat');

        $source = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => 'UTS Jaringan Komputer',
            'acak_opsi' => true,
            'auto_grade_essay' => true,
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $source->id,
            'nama' => 'Sesi Berjalan Pengunci',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-pengunci-duplikat',
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($source->isLocked());

        $pg = Question::create([
            'quiz_id' => $source->id,
            'organization_id' => $organization->id,
            'teks' => 'Perhatikan topologi berikut.',
            'tipe' => 'pilihan_ganda',
            'bobot' => 40,
            'durasi_detik' => 60,
            'pembahasan' => 'Switch bekerja pada layer data-link.',
        ]);

        $sourcePath = "question-media/{$organization->id}/topologi-asli.png";
        Storage::disk('local')->put($sourcePath, 'fake-png-bytes');

        $sourceMedia = QuestionMedia::create([
            'organization_id' => $organization->id,
            'question_id' => $pg->id,
            'path' => $sourcePath,
            'mime_type' => 'image/png',
            'size_bytes' => 14,
            'original_name' => 'topologi.png',
        ]);

        $pg->update([
            'content_blocks_json' => [
                ['type' => 'text', 'text' => 'Analisis gambar berikut:'],
                ['type' => 'image', 'media_id' => $sourceMedia->id, 'alt' => 'Topologi LAN'],
            ],
        ]);

        Option::create(['question_id' => $pg->id, 'teks' => 'Switch', 'is_correct' => true]);
        Option::create(['question_id' => $pg->id, 'teks' => 'Repeater', 'is_correct' => false]);

        Question::create([
            'quiz_id' => $source->id,
            'organization_id' => $organization->id,
            'teks' => 'Uraikan perbedaan TCP dan UDP.',
            'tipe' => 'essay',
            'bobot' => 60,
            'durasi_detik' => 180,
            'kunci_essay' => 'TCP connection-oriented, UDP connectionless.',
            'rubrik_essay' => [
                ['kriteria' => 'Ketepatan konsep koneksi', 'skor' => 30],
                ['kriteria' => 'Contoh protokol aplikasi', 'skor' => 30],
            ],
            'petunjuk_json' => ['Sertakan minimal dua contoh protokol.'],
            'sub_soal_json' => [
                ['label' => 'a', 'teks' => 'Jelaskan mekanisme three-way handshake.', 'bobot' => 30],
                ['label' => 'b', 'teks' => 'Berikan skenario penggunaan UDP.', 'bobot' => 30],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.quizzes.duplicate', $source));

        $clone = Quiz::where('source_quiz_id', $source->id)->firstOrFail();

        $response->assertRedirect(route('admin.quizzes.questions.index', $clone));
        $this->assertSame('UTS Jaringan Komputer (Salinan)', $clone->judul);
        $this->assertFalse($clone->isLocked());
        $this->assertCount(2, $clone->questions);

        $clonedPg = $clone->questions()->where('tipe', 'pilihan_ganda')->with(['options', 'media'])->firstOrFail();
        $this->assertCount(2, $clonedPg->options);
        $this->assertSame('Switch', $clonedPg->correctOption()?->teks);
        $this->assertCount(1, $clonedPg->media);

        $clonedMedia = $clonedPg->media->first();
        $this->assertNotSame($sourceMedia->id, $clonedMedia->id);
        $this->assertNotSame($sourceMedia->path, $clonedMedia->path);
        Storage::disk('local')->assertExists($clonedMedia->path);
        Storage::disk('local')->assertExists($sourceMedia->path);

        $clonedEssay = $clone->questions()->where('tipe', 'essay')->firstOrFail();
        $this->assertCount(2, $clonedEssay->rubrik_essay ?? []);
        $this->assertCount(2, $clonedEssay->sub_soal_json ?? []);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'quiz_duplicated',
            'subject_type' => 'quiz',
            'subject_id' => $clone->id,
        ]);
    }

    public function test_duplikasi_kuis_ditolak_saat_kuota_penyimpanan_habis_atau_beda_tenant(): void
    {
        Storage::fake('local');
        config(['platform.storage_mb' => 0]);

        [$organization, $admin] = $this->buatTenant('kampus-penuh', 'admin-penuh');
        [$otherOrg, $otherAdmin] = $this->buatTenant('kampus-lain', 'admin-lain');

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => 'Kuis Bergambar',
            'created_by' => $admin->id,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Soal bergambar',
            'tipe' => 'pilihan_ganda',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $path = "question-media/{$organization->id}/gambar.png";
        Storage::disk('local')->put($path, '1234567890');
        $media = QuestionMedia::create([
            'organization_id' => $organization->id,
            'question_id' => $question->id,
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 10,
            'original_name' => 'gambar.png',
        ]);
        $question->update([
            'content_blocks_json' => [['type' => 'image', 'media_id' => $media->id]],
        ]);

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.quizzes.duplicate', $quiz))
            ->assertSessionHasErrors('kuis');

        $this->assertSame(1, Quiz::withoutGlobalScopes()->where('organization_id', $organization->id)->count());

        $this->actingAs($otherAdmin)
            ->withSession([
                'auth_token' => $otherAdmin->current_session_token,
                'organization_id' => $otherOrg->id,
            ])
            ->post(route('admin.quizzes.duplicate', $quiz))
            ->assertNotFound();
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function buatTenant(string $slug, string $username): array
    {
        $organization = Organization::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => $username.'-token',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $organization->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        return [$organization, $admin];
    }
}
