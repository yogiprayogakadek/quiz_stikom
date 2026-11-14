<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSessionPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_pratinjau_baca_saja_dengan_kunci_tersembunyi(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin');
        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Pagi',
            'pin' => 'PAGI01',
            'created_by' => $admin->id,
        ]);
        $quiz = $this->kuisDenganSoal($organization, $admin, 'UTS Matematika');
        $session = $this->sesi($organization, $admin, $quiz, $kelas, 'Pratinjau UTS');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.preview', $session))
            ->assertOk()
            ->assertSee('Pratinjau UTS')
            ->assertSee('UTS Matematika')
            ->assertSee('Kelas Pagi')
            ->assertSee('Berapa hasil 2 + 2?')
            ->assertSee('Empat')
            ->assertSee('Jelaskan proses fotosintesis.')
            ->assertSee('Kunci essay rahasia')
            ->assertSee('Ketepatan penjelasan')
            ->assertSee('Ringkasan &amp; soal', false)
            ->assertSee('Contoh tampilan peserta')
            ->assertSee('Tampilkan kunci')
            ->assertSee('x-show="kunci"', false)
            ->assertSee('x-cloak', false)
            ->assertSee('Daftar mengikuti kuis.');

        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_tombol_pratinjau_tampil_di_ruang_kendali(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin');
        $quiz = $this->kuisDenganSoal($organization, $admin, 'Kuis Tombol');
        $session = $this->sesi($organization, $admin, $quiz);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.show', $session))
            ->assertOk()
            ->assertSee('Pratinjau')
            ->assertSee(route('admin.sessions.preview', $session), false);
    }

    public function test_pratinjau_serempak_memakai_soal_yang_terkunci_di_sesi(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin');
        $quiz = $this->kuisDenganSoal($organization, $admin, 'Kuis Serempak');
        [$pg, $essay] = $quiz->questions()->orderBy('id')->get()->all();

        $session = $this->sesi($organization, $admin, $quiz, null, 'Sesi Berjalan', 'serempak', 'berjalan');

        SessionQuestion::create([
            'quiz_session_id' => $session->id,
            'question_id' => $pg->id,
            'urutan' => 1,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.preview', $session))
            ->assertOk()
            ->assertSee('Berapa hasil 2 + 2?')
            ->assertDontSee('Jelaskan proses fotosintesis.')
            ->assertSee('Sesi memakai 1 dari 2 soal.')
            ->assertSee('1 soal');
    }

    public function test_pratinjau_memperingatkan_essay_pada_mode_serempak(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin');
        $quiz = $this->kuisDenganSoal($organization, $admin, 'Kuis Campuran');
        $session = $this->sesi($organization, $admin, $quiz, null, 'Sesi Campuran', 'serempak', 'draft');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.preview', $session))
            ->assertOk()
            ->assertSee('Mode Serempak tidak bisa dimulai selama kuis memuat soal essay.');
    }

    public function test_admin_organisasi_lain_tidak_bisa_membuka_pratinjau(): void
    {
        [$organizationA, $adminA] = $this->organizationWithUser('alpha', 'admin');
        [, $adminB] = $this->organizationWithUser('beta', 'admin');

        $quiz = $this->kuisDenganSoal($organizationA, $adminA, 'Kuis Alpha');
        $session = $this->sesi($organizationA, $adminA, $quiz);

        $this->actingAs($adminB)
            ->withSession(['auth_token' => $adminB->current_session_token, 'organization_id' => $adminB->organizations()->first()->id])
            ->get(route('admin.sessions.preview', $session))
            ->assertNotFound();
    }

    public function test_peserta_tidak_bisa_membuka_pratinjau(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin');
        $peserta = User::create([
            'nama' => 'Peserta Alpha',
            'username' => 'peserta_alpha',
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-alpha-token',
        ]);
        $organization->users()->attach($peserta->id, ['role' => 'participant', 'status' => 'active']);
        $quiz = $this->kuisDenganSoal($organization, $admin, 'Kuis Peserta');
        $session = $this->sesi($organization, $admin, $quiz);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.preview', $session))
            ->assertForbidden();
    }

    private function kuisDenganSoal(Organization $organization, User $admin, string $judul): Quiz
    {
        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => $judul,
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Berapa hasil 2 + 2?',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 60,
            'bobot' => 25,
        ]);

        $pg->options()->createMany([
            ['teks' => 'Tiga', 'is_correct' => false],
            ['teks' => 'Empat', 'is_correct' => true],
        ]);

        Question::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan proses fotosintesis.',
            'tipe' => 'essay',
            'durasi_detik' => 300,
            'bobot' => 75,
            'kunci_essay' => 'Kunci essay rahasia',
            'rubrik_essay' => [
                ['label' => 'Ketepatan penjelasan', 'points' => 50],
                ['label' => 'Kelengkapan', 'points' => 25],
            ],
        ]);

        return $quiz;
    }

    private function sesi(
        Organization $organization,
        User $admin,
        Quiz $quiz,
        ?Kelas $kelas = null,
        string $nama = 'Sesi Pratinjau',
        string $mode = 'acak',
        string $status = 'draft',
    ): QuizSession {
        return QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas?->id,
            'nama' => $nama,
            'mode' => $mode,
            'status' => $status,
            'display_token' => 'token-'.$quiz->id.'-'.$status,
            'created_by' => $admin->id,
        ]);
    }

    private function organizationWithUser(string $slug, string $role): array
    {
        $organization = Organization::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        $user = User::create([
            'nama' => ucfirst($role).' '.ucfirst($slug),
            'username' => $role.'_'.$slug,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $role.'-'.$slug.'-token',
        ]);
        $organization->users()->attach($user->id, [
            'role' => $role === 'admin' ? 'owner' : 'participant',
            'status' => 'active',
        ]);

        return [$organization, $user];
    }
}
