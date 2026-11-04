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
}
