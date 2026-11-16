<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Organization;
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

    public function test_daftar_sesi_bisa_difilter_per_jenis(): void
    {
        $admin = $this->admin();

        $kuisNyata = Quiz::create(['judul' => 'Kuis Nyata', 'is_percobaan' => false, 'created_by' => $admin->id]);
        $kuisSimulasi = Quiz::create(['judul' => 'Kuis Simulasi', 'is_percobaan' => true, 'created_by' => $admin->id]);

        QuizSession::create([
            'quiz_id' => $kuisNyata->id,
            'nama' => 'Sesi Nyata',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-sesi-nyata',
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisSimulasi->id,
            'nama' => 'Sesi Simulasi',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-sesi-simulasi',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', ['jenis' => 'real']))
            ->assertOk()
            ->assertSee('Sesi Nyata')
            ->assertDontSee('Sesi Simulasi')
            ->assertSee('data-search="sesi nyata', false);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', ['jenis' => 'simulasi']))
            ->assertOk()
            ->assertSee('Sesi Simulasi')
            ->assertDontSee('Sesi Nyata');

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', ['jenis' => 'tidak-dikenal']))
            ->assertOk()
            ->assertSee('Sesi Nyata')
            ->assertSee('Sesi Simulasi');
    }

    public function test_filter_jenis_berlaku_bersama_status_dan_kelas(): void
    {
        $admin = $this->admin();
        $kelasA = Kelas::create(['nama' => 'Kelas Alpha', 'pin' => 'ALPHA1']);
        $kelasB = Kelas::create(['nama' => 'Kelas Beta', 'pin' => 'BETA01']);

        $kuisNyata = Quiz::create(['judul' => 'Kuis Nyata', 'created_by' => $admin->id]);
        $kuisSimulasi = Quiz::create(['judul' => 'Kuis Simulasi', 'is_percobaan' => true, 'created_by' => $admin->id]);

        QuizSession::create([
            'quiz_id' => $kuisNyata->id,
            'kelas_id' => $kelasA->id,
            'nama' => 'Nyata Alpha Lobby',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-nyata-alpha-lobby',
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisSimulasi->id,
            'kelas_id' => $kelasA->id,
            'nama' => 'Simulasi Alpha Lobby',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-simulasi-alpha-lobby',
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisNyata->id,
            'kelas_id' => $kelasA->id,
            'nama' => 'Nyata Alpha Selesai',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-nyata-alpha-selesai',
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisSimulasi->id,
            'kelas_id' => $kelasB->id,
            'nama' => 'Simulasi Beta Lobby',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-simulasi-beta-lobby',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', [
                'kelas' => $kelasA->id,
                'status' => 'lobby',
                'jenis' => 'real',
            ]));

        $response->assertOk()
            ->assertSee('Nyata Alpha Lobby')
            ->assertDontSee('Simulasi Alpha Lobby')
            ->assertDontSee('Nyata Alpha Selesai')
            ->assertDontSee('Simulasi Beta Lobby')
            ->assertSee(route('admin.sessions.index', [
                'status' => 'lobby',
                'kelas' => $kelasA->id,
                'jenis' => 'simulasi',
            ]))
            ->assertSee(route('admin.sessions.index', [
                'status' => 'selesai',
                'kelas' => $kelasA->id,
                'jenis' => 'real',
            ]))
            ->assertSee('<input type="hidden" name="jenis" value="real">', false);
    }

    public function test_empty_state_filter_jenis_menyebut_jenis_aktif(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['nama' => 'Kelas Simulasi', 'pin' => 'SIMS01']);
        $kuisSimulasi = Quiz::create(['judul' => 'Kuis Simulasi', 'is_percobaan' => true, 'created_by' => $admin->id]);

        QuizSession::create([
            'quiz_id' => $kuisSimulasi->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Simulasi Kelas',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-simulasi-kelas',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', ['jenis' => 'real']))
            ->assertOk()
            ->assertSee('Filter jenis ujian nyata sedang aktif.')
            ->assertDontSee('Simulasi Kelas');
    }

    public function test_daftar_sesi_terisolasi_per_organisasi_dan_hanya_untuk_admin(): void
    {
        $admin = $this->admin();

        $kuisLokal = Quiz::create(['judul' => 'Kuis Lokal', 'created_by' => $admin->id]);
        QuizSession::create([
            'quiz_id' => $kuisLokal->id,
            'nama' => 'Sesi Lokal',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-sesi-lokal',
            'created_by' => $admin->id,
        ]);

        $organisasiLain = Organization::create([
            'name' => 'Organisasi Lain',
            'slug' => 'organisasi-lain',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $kuisAsing = Quiz::create([
            'judul' => 'Kuis Asing',
            'organization_id' => $organisasiLain->id,
            'created_by' => $admin->id,
        ]);

        QuizSession::create([
            'quiz_id' => $kuisAsing->id,
            'organization_id' => $organisasiLain->id,
            'nama' => 'Sesi Asing',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'token-sesi-asing',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-list'])
            ->get(route('admin.sessions.index', ['jenis' => 'real']))
            ->assertOk()
            ->assertSee('Sesi Lokal')
            ->assertDontSee('Sesi Asing');

        $peserta = User::create([
            'nama' => 'Peserta Filter',
            'username' => 'peserta-filter',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta-filter',
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta-filter', 'organization_id' => $organisasiLain->id])
            ->get(route('admin.sessions.index'))
            ->assertForbidden();
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
