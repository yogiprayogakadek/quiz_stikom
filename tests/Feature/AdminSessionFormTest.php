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

    public function test_form_sesi_memakai_pemilih_kuis_dan_kelas_yang_bisa_dicari(): void
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
            ->assertDontSee('id="quiz_id-cari"', false)
            ->assertDontSee('id="kelas_id-cari"', false)
            ->assertSee('for="quiz_id"', false)
            ->assertSee('for="kelas_id"', false)
            ->assertSee('id="quiz_id-pencarian"', false)
            ->assertSee('id="kelas_id-pencarian"', false)
            ->assertSee('placeholder="Cari kuis"', false)
            ->assertSee('placeholder="Cari kelas"', false)
            ->assertSee('data-search="uts matematika 1 soal"', false)
            ->assertSee('Kuis Kosong (0 soal) - belum ada soal')
            ->assertSee('Kelas Pagi')
            ->assertSee('aria-required="true"', false);
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

        $this->assertMatchesRegularExpression('/id="quiz_id-opsi-1"[\s\S]*?aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/id="kelas_id-opsi-1"[\s\S]*?aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/id="quiz_id"[\s\S]*?Kuis Sore[\s\S]*?<\/button>/', $html);
        $this->assertMatchesRegularExpression('/id="kelas_id"[\s\S]*?Kelas Sore[\s\S]*?<\/button>/', $html);
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
