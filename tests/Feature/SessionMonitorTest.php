<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_mengumpulkan_pekerjaan_peserta_secara_paksa(): void
    {
        [$admin, $session, $attempt] = $this->sesiAcakBerjalan();

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post("/admin/sessions/{$session->id}/peserta/{$attempt->id}/kumpulkan");

        $response->assertRedirect();

        $attempt->refresh();
        $this->assertSame('selesai', $attempt->status);
        $this->assertSame(1, $attempt->jumlah_benar);
        $this->assertSame(100, $attempt->skor_total);
    }

    public function test_admin_bisa_mengeluarkan_peserta_dari_sesi(): void
    {
        [$admin, $session, $attempt, $peserta, $lain] = $this->sesiAcakBerjalan();

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post("/admin/sessions/{$session->id}/peserta/{$attempt->id}/keluarkan");

        $response->assertRedirect();

        $this->assertDatabaseMissing('attempts', ['id' => $attempt->id]);

        $diizinkan = $session->fresh()->allowedParticipants()->pluck('users.id')->all();
        $this->assertSame([$lain->id], $diizinkan);
        $this->assertFalse($session->fresh()->isUserAllowed($peserta));
    }

    public function test_state_display_membawa_peringatan_terbaru_untuk_polling(): void
    {
        [, $session, $attempt, $peserta] = $this->sesiAcakBerjalan();

        AttemptEvent::create([
            'attempt_id' => $attempt->id,
            'tipe' => 'fullscreen_exit',
            'created_at' => now(),
        ]);

        $state = app(QuizEngine::class)->displayState($session->fresh());

        $this->assertCount(1, $state['peringatan']);

        $peringatan = $state['peringatan'][0];
        $this->assertSame($peserta->nama, $peringatan['nama']);
        $this->assertSame('Keluar dari layar penuh (Toleransi 10s berjalan)', $peringatan['pesan']);
        $this->assertSame('warning', $peringatan['level']);
        $this->assertSame(1, $peringatan['jumlah_pelanggaran']);
    }

    public function test_peserta_belum_gabung_tidak_bisa_memulai_mengerjakan(): void
    {
        [$session, $attempt, $peserta] = $this->sesiAcakMenungguAttempt();

        $response = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->post("/sesi/{$session->id}/mulai");

        $response->assertRedirect(route('peserta.dashboard'));
        $response->assertSessionHasErrors('gabung');

        $attempt->refresh();
        $this->assertSame('menunggu', $attempt->status);
        $this->assertNull($attempt->mulai_at);
    }

    public function test_peserta_harus_gabung_dulu_sebelum_mulai_mengerjakan(): void
    {
        [$session, $attempt, $peserta] = $this->sesiAcakMenungguAttempt();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->post("/sesi/{$session->id}/gabung")
            ->assertRedirect(route('peserta.dashboard'));

        $this->assertNotNull($attempt->fresh()->bergabung_at);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->post("/sesi/{$session->id}/mulai")
            ->assertRedirect(route('peserta.attempt.show', $attempt));

        $this->assertSame('berjalan', $attempt->fresh()->status);
        $this->assertSame(1, $attempt->attemptQuestions()->count());
    }

    public function test_dashboard_menyembunyikan_attempt_yang_belum_digabung(): void
    {
        [$session, , $peserta] = $this->sesiAcakMenungguAttempt();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->getJson('/dashboard/state')
            ->assertOk()
            ->assertJsonPath('sesi_tersedia.0.id', $session->id)
            ->assertJsonPath('sesi_tersedia.0.attempt', null);
    }

    /**
     * @return array{0: User, 1: QuizSession, 2: Attempt, 3: User, 4: User}
     */
    private function sesiAcakBerjalan(): array
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-uji',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Uji',
            'username' => 'peserta-uji',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
        ]);

        $lain = User::create([
            'nama' => 'Peserta Lain',
            'username' => 'peserta-lain',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
        ]);

        $question = Question::create([
            'teks' => 'Ibu kota Indonesia?',
            'durasi_detik' => 60,
        ]);

        $benar = Option::create([
            'question_id' => $question->id,
            'teks' => 'Jakarta',
            'is_correct' => true,
        ]);

        $salah = Option::create([
            'question_id' => $question->id,
            'teks' => 'Bandung',
            'is_correct' => false,
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Uji',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Uji',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-uji',
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
            'question_id' => $question->id,
            'urutan' => 1,
            'urutan_opsi_json' => [$benar->id, $salah->id],
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'option_id' => $benar->id,
            'waktu_jawab_ms' => 1000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        return [$admin, $session, $attempt, $peserta, $lain];
    }

    /**
     * Sesi acak yang sudah berjalan, dengan attempt yang disiapkan admin
     * (status menunggu) tetapi pesertanya belum menekan Gabung.
     *
     * @return array{0: QuizSession, 1: Attempt, 2: User}
     */
    private function sesiAcakMenungguAttempt(): array
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-uji',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Uji',
            'username' => 'peserta-uji',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);

        $question = Question::create([
            'teks' => 'Ibu kota Indonesia?',
            'durasi_detik' => 60,
        ]);

        Option::create([
            'question_id' => $question->id,
            'teks' => 'Jakarta',
            'is_correct' => true,
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Uji',
            'created_by' => $admin->id,
        ]);

        $quiz->questions()->attach($question->id);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Uji',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-uji',
            'created_by' => $admin->id,
        ]);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
        ]);

        return [$session, $attempt, $peserta];
    }
}
