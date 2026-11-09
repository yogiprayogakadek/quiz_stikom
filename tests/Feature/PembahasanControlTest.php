<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PembahasanControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_membuka_pembahasan_saat_sesi_berjalan(): void
    {
        $admin = $this->admin();
        $session = $this->sesi($admin, 'acak', [
            'status' => 'berjalan',
            'pembahasan_mode' => 'tersembunyi',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-pembahasan'])
            ->post(route('admin.sessions.pembahasan', $session), ['mode' => 'terbuka'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $fresh = $session->fresh();
        $this->assertSame('terbuka', $fresh->pembahasan_mode);
        $this->assertTrue($fresh->tampilkan_pembahasan);
    }

    public function test_pembahasan_otomatis_baru_terbuka_setelah_semua_peserta_selesai(): void
    {
        $admin = $this->admin();
        $session = $this->sesi($admin, 'acak', [
            'status' => 'berjalan',
            'pembahasan_mode' => 'otomatis',
        ]);

        $pertama = $this->attempt($session, 'Peserta Satu', 'berjalan');
        $kedua = $this->attempt($session, 'Peserta Dua', 'berjalan');

        $engine = app(QuizEngine::class);

        $engine->selesaikanAcak($pertama);

        $this->assertSame('otomatis', $session->fresh()->pembahasan_mode);
        $this->assertFalse($session->fresh()->pembahasanTampil());

        $engine->selesaikanAcak($kedua);

        $this->assertSame('terbuka', $session->fresh()->pembahasan_mode);
        $this->assertTrue($session->fresh()->pembahasanTampil());
    }

    public function test_display_menerima_seluruh_leaderboard_dan_lima_teratas(): void
    {
        $admin = $this->admin();
        $session = $this->sesi($admin, 'acak', [
            'status' => 'berjalan',
            'leaderboard_live' => true,
        ]);

        foreach (range(1, 7) as $i) {
            $attempt = $this->attempt($session, 'Peserta '.$i, 'selesai');
            $attempt->update([
                'skor_total' => 100 - $i,
                'selesai_at' => now()->getTimestampMs(),
            ]);
        }

        $state = app(QuizEngine::class)->displayState($session);

        $this->assertCount(7, $state['leaderboard']);
        $this->assertCount(5, $state['top5']);
        $this->assertSame('Peserta 1', $state['leaderboard'][0]['nama']);
        $this->assertSame(99, $state['leaderboard'][0]['skor']);
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin Pembahasan',
            'username' => 'admin-pembahasan',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-pembahasan',
        ]);
    }

    private function sesi(User $admin, string $mode, array $atribut = []): QuizSession
    {
        $quiz = Quiz::create([
            'judul' => 'Kuis Pembahasan '.uniqid(),
            'created_by' => $admin->id,
        ]);

        return QuizSession::create(array_merge([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Pembahasan',
            'mode' => $mode,
            'status' => 'berjalan',
            'display_token' => 'token-pembahasan-'.uniqid(),
            'dibuka_at' => now(),
            'created_by' => $admin->id,
        ], $atribut));
    }

    private function attempt(QuizSession $session, string $nama, string $status): Attempt
    {
        $peserta = User::create([
            'nama' => $nama,
            'username' => 'peserta-'.uniqid(),
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
        ]);

        return Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => $status,
            'bergabung_at' => now(),
        ]);
    }
}
