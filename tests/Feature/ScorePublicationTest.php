<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\AttemptResetService;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScorePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_baru_menyembunyikan_nilai_dan_admin_dapat_menampilkan_lalu_menyembunyikannya(): void
    {
        [$admin, $peserta, $session, $attempt] = $this->dataSesi();

        $this->assertFalse($session->nilaiTampil());

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertTrue($session->fresh()->nilaiTampil());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session_score_visibility_changed',
            'subject_type' => 'quiz_session',
            'subject_id' => $session->id,
            'actor_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'disembunyikan'])
            ->assertRedirect();

        $this->assertFalse($session->fresh()->nilaiTampil());
        $this->assertSame(2, AuditLog::where('action', 'session_score_visibility_changed')->count());
    }

    public function test_nilai_tidak_dapat_dipublikasikan_sebelum_sesi_dan_penilaian_essay_final(): void
    {
        [$admin, , $session, $attempt] = $this->dataSesi();
        $session->update(['status' => 'berjalan']);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertSessionHasErrors('nilai');

        $session->update(['status' => 'selesai']);
        $essay = Question::create([
            'quiz_id' => $session->quiz_id,
            'teks' => 'Jelaskan topologi jaringan.',
            'tipe' => 'essay',
            'bobot' => 10,
        ]);
        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'urutan' => 2,
            'urutan_opsi_json' => [],
        ]);
        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => 'Topologi bintang memakai satu perangkat pusat.',
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertSessionHasErrors('nilai');

        $answer->update(['dinilai_at' => now(), 'dinilai_oleh' => $admin->id, 'skor' => 8]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($session->fresh()->nilaiTampil());
    }

    public function test_hasil_riwayat_state_dan_display_tidak_membocorkan_nilai_yang_disembunyikan(): void
    {
        [, $peserta, $session, $attempt] = $this->dataSesi();
        $session->update(['pembahasan_mode' => 'terbuka', 'tampilkan_pembahasan' => true]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('data-score-hidden', false)
            ->assertSee('Nilai belum dipublikasikan')
            ->assertDontSee('data-final-score', false)
            ->assertDontSee('pill pill-leaf">Benar', false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->assertSee('Belum dipublikasikan')
            ->assertDontSee('data-nilai-akhir', false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->getJson(route('peserta.session.state', $session))
            ->assertOk()
            ->assertJsonMissingPath('skor_total')
            ->assertJsonMissingPath('jumlah_benar');

        $display = app(QuizEngine::class)->displayState($session->fresh());
        $this->assertFalse($display['nilai_ditampilkan']);
        $this->assertSame([], $display['leaderboard']);
        $this->assertSame([], $display['histogram']);
        $this->assertSame([], $display['soal_terendah']);
    }

    public function test_nilai_muncul_setelah_dipublikasikan_dan_reset_menyembunyikannya_lagi(): void
    {
        [, $peserta, $session, $attempt] = $this->dataSesi(['nilai_ditampilkan' => true]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('data-final-score', false)
            ->assertSee('87');

        app(AttemptResetService::class)->reset($attempt, 'fresh');

        $this->assertFalse($session->fresh()->nilaiTampil());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session_score_visibility_changed',
            'subject_id' => $session->id,
        ]);
    }

    public function test_dashboard_peserta_hanya_menghitung_nilai_yang_sudah_dipublikasikan(): void
    {
        [$admin, $peserta, $session, $attempt] = $this->dataSesi();
        $organization = Organization::create([
            'name' => 'Workspace Nilai',
            'slug' => 'workspace-nilai',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $organization->users()->attach([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
            $peserta->id => ['role' => 'participant', 'status' => 'active'],
        ]);
        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Nilai',
            'pin' => 'NILAI1',
            'created_by' => $admin->id,
        ]);
        $kelas->peserta()->attach($peserta->id);
        $session->quiz->update(['organization_id' => $organization->id, 'kelas_id' => $kelas->id]);
        $session->update(['organization_id' => $organization->id, 'kelas_id' => $kelas->id]);
        $attempt->update(['organization_id' => $organization->id]);

        $response = $this->actingAs($peserta)
            ->withSession([
                'auth_token' => $peserta->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total_skor'] === 0)
            ->assertViewHas('leaderboard', fn ($rows) => $rows->isEmpty());

        $session->update(['nilai_ditampilkan' => true]);

        $response = $this->actingAs($peserta)
            ->withSession([
                'auth_token' => $peserta->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total_skor'] === 87)
            ->assertViewHas('leaderboard', fn ($rows) => $rows->pluck('id')->contains($peserta->id));
    }

    public function test_publikasi_nilai_tetap_terisolasi_per_workspace_dan_role(): void
    {
        [$admin, $peserta, $session] = $this->dataSesi();
        $organization = Organization::create([
            'name' => 'Workspace Pemilik',
            'slug' => 'workspace-pemilik',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $organizationLain = Organization::create([
            'name' => 'Workspace Lain',
            'slug' => 'workspace-lain',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $adminLain = User::create([
            'nama' => 'Admin Lain',
            'username' => 'admin-lain',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-lain',
        ]);
        $organization->users()->attach([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
            $peserta->id => ['role' => 'participant', 'status' => 'active'],
        ]);
        $organizationLain->users()->attach($adminLain->id, ['role' => 'owner', 'status' => 'active']);
        $session->quiz->update(['organization_id' => $organization->id]);
        $session->update(['organization_id' => $organization->id]);

        $this->actingAs($adminLain)
            ->withSession([
                'auth_token' => $adminLain->current_session_token,
                'organization_id' => $organizationLain->id,
            ])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertNotFound();

        $this->actingAs($peserta)
            ->withSession([
                'auth_token' => $peserta->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertForbidden();

        $this->assertFalse($session->fresh()->nilaiTampil());
    }

    private function dataSesi(array $sessionAttributes = []): array
    {
        $admin = User::create([
            'nama' => 'Admin Nilai',
            'username' => 'admin-nilai-'.uniqid(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-nilai',
        ]);
        $peserta = User::create([
            'nama' => 'Peserta Nilai',
            'username' => 'peserta-nilai-'.uniqid(),
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta-nilai',
        ]);
        $quiz = Quiz::create([
            'judul' => 'Kuis Publikasi Nilai',
            'created_by' => $admin->id,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Perangkat yang meneruskan paket antarjaringan?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 10,
        ]);
        $option = Option::create([
            'question_id' => $question->id,
            'teks' => 'Router',
            'is_correct' => true,
        ]);
        $session = QuizSession::create(array_merge([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Publikasi Nilai',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'display-nilai-'.uniqid(),
            'ditutup_at' => now(),
            'created_by' => $admin->id,
        ], $sessionAttributes));
        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'mulai_at' => now()->subMinutes(10)->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
            'jumlah_benar' => 1,
            'skor_total' => 87,
            'nilai' => 87,
            'poin_total' => 10,
            'poin_maksimal' => 10,
        ]);
        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'urutan' => 1,
            'urutan_opsi_json' => [$option->id],
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'option_id' => $option->id,
            'skor' => 10,
            'answered_at' => now(),
        ]);

        return [$admin, $peserta, $session, $attempt];
    }
}
