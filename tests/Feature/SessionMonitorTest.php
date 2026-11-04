<?php

namespace Tests\Feature;

use App\Jobs\CloseQuestionJob;
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
use Illuminate\Support\Facades\Queue;
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

    public function test_peserta_belum_gabung_tidak_bisa_masuk_sesi_serempak(): void
    {
        [$session, , $peserta] = $this->sesiSerempakMenungguAttempt();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-serempak'])
            ->get("/sesi/{$session->id}/kerjakan")
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHasErrors('gabung');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-serempak'])
            ->getJson("/sesi/{$session->id}/state")
            ->assertNotFound();
    }

    public function test_monitor_admin_menampilkan_peserta_yang_sudah_dan_belum_gabung(): void
    {
        [$admin, $peserta, $lain, $session] = $this->sesiSerempakLobby();

        Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
            'bergabung_at' => now(),
        ]);

        Attempt::create([
            'user_id' => $lain->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
        ]);

        $html = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-serempak'])
            ->getJson("/admin/sessions/{$session->id}/monitor")
            ->assertOk()
            ->assertJsonStructure(['status', 'peserta'])
            ->json('peserta');

        $this->assertStringContainsString('Peserta Serempak', $html);
        $this->assertStringContainsString('Peserta Lain Serempak', $html);
        $this->assertStringContainsString('Belum bergabung', $html);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-serempak'])
            ->get("/admin/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Peserta di sesi ini')
            ->assertSee('id="monitor-peserta"', false)
            ->assertSee('Belum bergabung');
    }

    public function test_peserta_belum_gabung_tidak_menahan_soal_serempak(): void
    {
        Queue::fake();

        [$admin, $peserta, $lain, $session] = $this->sesiSerempakLobby();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-serempak'])
            ->post("/sesi/{$session->id}/gabung")
            ->assertRedirect(route('peserta.session.play', $session));

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-serempak'])
            ->post("/admin/sessions/{$session->id}/mulai", ['peserta_ids' => [$peserta->id, $lain->id]])
            ->assertRedirect();

        $attemptPeserta = Attempt::where('user_id', $peserta->id)->firstOrFail();
        $attemptLain = Attempt::where('user_id', $lain->id)->firstOrFail();

        $this->assertSame('berjalan', $attemptPeserta->status);
        $this->assertSame('menunggu', $attemptLain->status);
        $this->assertSame(0, $attemptLain->attemptQuestions()->count());

        $this->getJson("/display/{$session->fresh()->display_token}/state")
            ->assertOk()
            ->assertJsonPath('total_peserta', 1)
            ->assertJsonPath('total', 1);

        $soal = $attemptPeserta->attemptQuestions()->firstOrFail();

        Queue::assertPushedTimes(CloseQuestionJob::class, 1);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-serempak'])
            ->postJson("/sesi/{$session->id}/jawab", [
                'question_id' => $soal->question_id,
                'option_id' => $soal->urutan_opsi_json[0],
            ])
            ->assertOk()
            ->assertJsonPath('total_peserta', 1);

        Queue::assertPushedTimes(CloseQuestionJob::class, 2);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-serempak'])
            ->post("/admin/sessions/{$session->id}/tutup")
            ->assertRedirect();

        $this->assertSame('menunggu', $attemptLain->fresh()->status);
        $this->assertSame('selesai', $attemptPeserta->fresh()->status);
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

    /**
     * Sesi serempak di lobby dengan soal yang sudah menempel di kuis.
     *
     * @return array{0: User, 1: User, 2: User, 3: QuizSession}
     */
    private function sesiSerempakLobby(): array
    {
        $admin = User::create([
            'nama' => 'Admin Serempak',
            'username' => 'admin-serempak',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-serempak',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Serempak',
            'username' => 'peserta-serempak',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-serempak',
        ]);

        $lain = User::create([
            'nama' => 'Peserta Lain Serempak',
            'username' => 'peserta-lain-serempak',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
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

        Option::create([
            'question_id' => $question->id,
            'teks' => 'Bandung',
            'is_correct' => false,
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Serempak Uji',
            'created_by' => $admin->id,
        ]);

        $quiz->questions()->attach($question->id);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Serempak Uji',
            'mode' => 'serempak',
            'status' => 'lobby',
            'display_token' => 'tokenserempak',
            'created_by' => $admin->id,
        ]);

        return [$admin, $peserta, $lain, $session];
    }

    /**
     * @return array{0: QuizSession, 1: Attempt, 2: User}
     */
    private function sesiSerempakMenungguAttempt(): array
    {
        [, $peserta, , $session] = $this->sesiSerempakLobby();

        $session->update(['status' => 'berjalan']);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
        ]);

        return [$session, $attempt, $peserta];
    }
}
