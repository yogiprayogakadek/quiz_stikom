<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminListFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_kuis_bisa_difilter_per_kelas(): void
    {
        $admin = $this->admin();
        $kelasA = Kelas::create(['nama' => 'Kelas Alpha', 'pin' => 'ALPHA1']);
        $kelasB = Kelas::create(['nama' => 'Kelas Beta', 'pin' => 'BETA01']);

        Quiz::create(['kelas_id' => $kelasA->id, 'judul' => 'Kuis Alpha', 'created_by' => $admin->id]);
        Quiz::create(['kelas_id' => $kelasB->id, 'judul' => 'Kuis Beta', 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.quizzes.index', ['kelas' => $kelasA->id]));

        $response->assertOk()
            ->assertSee('Kuis Alpha')
            ->assertDontSee('Kuis Beta')
            ->assertSee('Kelas Alpha')
            ->assertSee('data-item-key="kuis-', false);
    }

    public function test_daftar_sesi_bisa_difilter_per_kelas_dan_status(): void
    {
        $admin = $this->admin();
        $kelasA = Kelas::create(['nama' => 'Kelas Alpha', 'pin' => 'ALPHA1']);
        $kelasB = Kelas::create(['nama' => 'Kelas Beta', 'pin' => 'BETA01']);

        $kuisA = Quiz::create(['kelas_id' => $kelasA->id, 'judul' => 'Kuis Alpha', 'created_by' => $admin->id]);
        $kuisB = Quiz::create(['kelas_id' => $kelasB->id, 'judul' => 'Kuis Beta', 'created_by' => $admin->id]);

        QuizSession::create([
            'quiz_id' => $kuisA->id,
            'kelas_id' => $kelasA->id,
            'nama' => 'Sesi Alpha Lobby',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-sesi-alpha-lobby',
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisA->id,
            'kelas_id' => $kelasA->id,
            'nama' => 'Sesi Alpha Selesai',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-sesi-alpha-selesai',
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisB->id,
            'kelas_id' => $kelasB->id,
            'nama' => 'Sesi Beta Lobby',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-sesi-beta-lobby',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', ['kelas' => $kelasA->id, 'status' => 'lobby']));

        $response->assertOk()
            ->assertSee('Sesi Alpha Lobby')
            ->assertDontSee('Sesi Alpha Selesai')
            ->assertDontSee('Sesi Beta Lobby')
            ->assertSee(route('admin.sessions.index', ['status' => 'selesai', 'kelas' => $kelasA->id]))
            ->assertSee('data-item-key="sesi-', false);
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin Filter',
            'username' => 'admin-filter',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-list',
        ]);
    }
}
