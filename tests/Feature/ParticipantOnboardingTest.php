<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Database\Seeders\TrialQuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParticipantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tur_peserta_otomatis_sekali_dan_bisa_diselesaikan(): void
    {
        $peserta = $this->peserta();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertViewHas('tourBaru', true)
            ->assertSee('window.pesertaTourSelesaiUrl', false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.tour.selesai'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($peserta->fresh()->tour_completed_at);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertViewHas('tourBaru', false);
    }

    public function test_seeder_kuis_uji_coba_idempoten_dan_siap_dikerjakan(): void
    {
        User::create([
            'nama' => 'Admin',
            'username' => 'admin-uji',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
        ]);

        $this->seed(TrialQuizSeeder::class);
        $this->seed(TrialQuizSeeder::class);

        $quiz = Quiz::where('judul', 'Kuis Uji Coba')->firstOrFail();

        $this->assertSame(3, $quiz->questions()->count());
        $this->assertSame(12, $quiz->questions()->withCount('options')->get()->sum('options_count'));

        $session = QuizSession::where('quiz_id', $quiz->id)->where('nama', 'Uji Coba Sistem')->firstOrFail();

        $this->assertSame('berjalan', $session->status);
        $this->assertTrue($session->tampilkan_pembahasan);
        $this->assertNotNull($session->display_token);
    }

    private function peserta(): User
    {
        return User::create([
            'nama' => 'Peserta Baru',
            'username' => 'peserta-baru',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);
    }
}
