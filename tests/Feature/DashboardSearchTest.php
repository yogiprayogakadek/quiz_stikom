<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_mencari_kuis_sesi_peserta_dan_kelas(): void
    {
        $admin = User::create([
            'nama' => 'Admin Utama',
            'username' => 'admin_search',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-search-token',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Teknik Informatika 1A',
            'pin' => '1234',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Pemrograman Berorientasi Objek (PBO)',
            'jumlah_soal' => 5,
            'acak_opsi' => true,
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Ujian Tengah Semester PBO',
            'mode' => 'serempak',
            'acak_opsi' => true,
            'status' => 'berjalan',
            'display_token' => 'display-token-pbo',
            'created_by' => $admin->id,
        ]);

        $peserta = User::create([
            'nama' => 'Yogi Prayoga',
            'username' => '160010264',
            'password' => Hash::make('password'),
            'role' => 'peserta',
        ]);

        // Search for "PBO" -> should find quiz & session
        $res1 = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-search-token'])
            ->getJson(route('admin.dashboard.search', ['q' => 'PBO']));

        $res1->assertOk();
        $data1 = $res1->json();
        $this->assertGreaterThan(0, $data1['total']);
        $this->assertNotEmpty($data1['sesi']);
        $this->assertNotEmpty($data1['kuis']);

        // Search for "yogi" -> should find participant
        $res2 = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-search-token'])
            ->getJson(route('admin.dashboard.search', ['q' => 'yogi']));

        $res2->assertOk();
        $data2 = $res2->json();
        $this->assertGreaterThan(0, $data2['total']);
        $this->assertNotEmpty($data2['peserta']);
        $this->assertSame('Yogi Prayoga', $data2['peserta'][0]['nama']);

        // Search for PIN -> should find class
        $resPin = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-search-token'])
            ->getJson(route('admin.dashboard.search', ['q' => '1234']));

        $resPin->assertOk();
        $this->assertNotEmpty($resPin->json('kelas'));

        // Search with empty query -> returns total 0
        $res3 = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-search-token'])
            ->getJson(route('admin.dashboard.search', ['q' => '']));

        $res3->assertOk();
        $this->assertSame(0, $res3->json('total'));
    }

    public function test_peserta_tidak_bisa_mengakses_admin_dashboard_search(): void
    {
        $peserta = User::create([
            'nama' => 'Siswa Test',
            'username' => 'siswa_search',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-token',
        ]);

        $res = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-token'])
            ->getJson(route('admin.dashboard.search', ['q' => 'test']));

        $res->assertForbidden();
    }
}
