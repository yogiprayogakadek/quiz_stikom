<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSessionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_sesi_memakai_pencarian_kuis_dan_kelas(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['nama' => 'Kelas Pagi', 'pin' => 'PAGI01', 'created_by' => $admin->id]);
        $kuis = Quiz::create(['judul' => 'UTS Matematika', 'created_by' => $admin->id]);
        Question::create([
            'quiz_id' => $kuis->id,
            'teks' => 'Berapa 2 + 2?',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 60,
            'bobot' => 10,
        ]);
        Quiz::create(['judul' => 'Kuis Kosong', 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get('/admin/sessions/create');

        $response->assertOk()
            ->assertSee('Cari kuis')
            ->assertSee('Cari kelas')
            ->assertSee('id="quiz_id-cari"', false)
            ->assertSee('id="kelas_id-cari"', false)
            ->assertSee('data-search="uts matematika 1 soal"', false)
            ->assertSee('Kuis Kosong (0 soal) - belum ada soal')
            ->assertSee('Kelas Pagi');
    }

    public function test_form_edit_menandai_kuis_dan_kelas_terpilih(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['nama' => 'Kelas Sore', 'pin' => 'SORE01', 'created_by' => $admin->id]);
        $kuis = Quiz::create(['judul' => 'Kuis Sore', 'created_by' => $admin->id]);

        $session = QuizSession::create([
            'quiz_id' => $kuis->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Sore',
            'mode' => 'acak',
            'status' => 'draft',
            'display_token' => 'token-sore',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.sessions.edit', $session));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/value="'.$kuis->id.'"\s+data-search="[^"]*"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="'.$kelas->id.'"\s+data-search="[^"]*"\s+selected/', $html);
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin Sesi',
            'username' => 'admin-sesi',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-sesi',
        ]);
    }
}
