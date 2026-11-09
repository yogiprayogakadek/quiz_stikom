<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PesertaStartSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mulai_ujian_acak_saat_lobby_menampilkan_notifikasi(): void
    {
        [$peserta, $session] = $this->buatSesi('acak');

        $response = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->from(route('peserta.dashboard'))
            ->followingRedirects()
            ->post(route('peserta.attempt.start', $session));

        $response->assertOk();
        $response->assertSee('Sesi belum dimulai oleh admin. Mohon tunggu hingga admin memulai sesi.');

        $this->assertSame(
            'menunggu',
            Attempt::where('user_id', $peserta->id)->where('quiz_session_id', $session->id)->value('status')
        );
    }

    public function test_ruang_serempak_saat_lobby_menampilkan_status_menunggu_admin(): void
    {
        [$peserta, $session] = $this->buatSesi('serempak');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->post(route('peserta.attempt.start', $session))
            ->assertRedirect(route('peserta.session.play', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.session.play', $session))
            ->assertOk()
            ->assertSee('Menunggu admin memulai');
    }

    private function buatSesi(string $mode): array
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-mulai',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta',
            'username' => 'peserta-mulai',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Mulai',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Mulai',
            'mode' => $mode,
            'status' => 'lobby',
            'display_token' => 'token-sesi-mulai-'.$mode,
            'dibuka_at' => now(),
            'created_by' => $admin->id,
        ]);

        Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
            'bergabung_at' => now(),
        ]);

        return [$peserta, $session];
    }
}
