<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizGrade;
use App\Models\QuizSession;
use App\Models\SessionQuestion;
use App\Models\User;
use App\Services\NilaiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RemedialTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_mengaktifkan_remidi_pada_kuis(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->put(route('admin.quizzes.update', $quiz), [
                'kelas_id' => $kelas->id,
                'judul' => $quiz->judul,
                'remidi_aktif' => 1,
                'remidi_nilai_min' => 70,
            ])
            ->assertRedirect(route('admin.quizzes.index'));

        $this->assertTrue($quiz->fresh()->remidi_aktif);
        $this->assertSame(70, $quiz->fresh()->remidi_nilai_min);
    }

    public function test_nilai_service_menormalkan_serempak(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar();
        $peserta = $this->peserta('peserta-normal');
        $session = $this->sesi($quiz, $admin, 'serempak', 'selesai');

        foreach ([1, 2] as $urutan) {
            $soal = Question::create([
                'quiz_id' => $quiz->id,
                'teks' => 'Soal '.$urutan,
                'tipe' => 'pilihan_ganda',
                'durasi_detik' => 30,
                'bobot' => 1,
            ]);

            SessionQuestion::create([
                'quiz_session_id' => $session->id,
                'question_id' => $soal->id,
                'urutan' => $urutan,
            ]);
        }

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => 1500,
        ]);

        $this->assertSame(75, app(NilaiService::class)->attempt($attempt));
    }

    public function test_admin_membuka_wizard_remidi(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-wizard');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->attempt($peserta, $awal, 50);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.sessions.remidi.create', $awal))
            ->assertOk()
            ->assertSee('Buat sesi remidi')
            ->assertSee('Peserta '.$peserta->username);
    }

    public function test_admin_membuat_sesi_remidi_dengan_soal_lama(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-remidi');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->attempt($peserta, $awal, 50);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.sessions.remidi.store', $awal), [
                'nama' => 'Remidi Kuis Algoritma',
                'sumber_soal' => 'lama',
                'peserta_ids' => [$peserta->id],
            ])
            ->assertRedirect();

        $remidi = QuizSession::where('jenis', 'remidi')->firstOrFail();

        $this->assertSame($awal->id, $remidi->remidi_dari_session_id);
        $this->assertSame('acak', $remidi->mode);
        $this->assertSame($awal->quiz_id, $remidi->quiz_id);
        $this->assertSame('lama', $remidi->remidi_soal);
        $this->assertTrue($remidi->allowedParticipants()->whereKey($peserta->id)->exists());
        $this->assertDatabaseHas('attempts', [
            'quiz_session_id' => $remidi->id,
            'user_id' => $peserta->id,
            'status' => 'menunggu',
        ]);
    }

    public function test_sesi_remidi_menolak_kuis_percobaan(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $quiz->update(['is_percobaan' => true]);
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.sessions.remidi.create', $awal))
            ->assertNotFound();
    }

    public function test_laporan_remidi_membandingkan_nilai_awal_dan_remidi(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-laporan');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->attempt($peserta, $awal, 50);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.sessions.remidi.store', $awal), [
                'nama' => 'Remidi Laporan',
                'sumber_soal' => 'lama',
                'peserta_ids' => [$peserta->id],
            ]);

        $remidi = QuizSession::where('jenis', 'remidi')->firstOrFail();
        $attemptRemidi = Attempt::where('quiz_session_id', $remidi->id)->firstOrFail();
        $attemptRemidi->update([
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => 80,
            'nilai' => 80,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.sessions.remidi.laporan', $remidi))
            ->assertOk()
            ->assertSee('50')
            ->assertSee('80')
            ->assertSee('30');
    }

    public function test_admin_menerapkan_nilai_terbaik(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-terbaik');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->attempt($peserta, $awal, 50);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.sessions.remidi.store', $awal), [
                'nama' => 'Remidi Terbaik',
                'sumber_soal' => 'lama',
                'peserta_ids' => [$peserta->id],
            ]);

        $remidi = QuizSession::where('jenis', 'remidi')->firstOrFail();
        Attempt::where('quiz_session_id', $remidi->id)->firstOrFail()->update([
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => 80,
            'nilai' => 80,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.sessions.remidi.terapkan', $remidi), [
                'user_ids' => [$peserta->id],
            ])
            ->assertRedirect();

        $grade = QuizGrade::where('sesi_awal_id', $awal->id)->where('user_id', $peserta->id)->firstOrFail();

        $this->assertSame(80, $grade->nilai_akhir);
        $this->assertSame('remidi', $grade->sumber);
        $this->assertSame(50, $grade->nilai_awal);
    }

    public function test_peserta_tanpa_nilai_remidi_dilewati(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-belum');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->attempt($peserta, $awal, 50);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.sessions.remidi.store', $awal), [
                'nama' => 'Remidi Belum Selesai',
                'sumber_soal' => 'lama',
                'peserta_ids' => [$peserta->id],
            ]);

        $remidi = QuizSession::where('jenis', 'remidi')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.sessions.remidi.terapkan', $remidi), [
                'user_ids' => [$peserta->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('quiz_grades', [
            'sesi_awal_id' => $awal->id,
            'user_id' => $peserta->id,
        ]);
    }

    public function test_dashboard_memakai_nilai_terbaik_remidi_sebagai_satu_kuis(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-dashboard-remidi');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');

        $this->attempt($peserta, $awal, 60);

        $quizRemidi = Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Soal Remidi Algoritma',
            'created_by' => $admin->id,
        ]);

        $remidi = QuizSession::create([
            'quiz_id' => $quizRemidi->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Remidi Dashboard',
            'mode' => 'acak',
            'jenis' => 'remidi',
            'remidi_dari_session_id' => $awal->id,
            'remidi_soal' => 'baru',
            'status' => 'selesai',
            'display_token' => 'token-remidi-dashboard',
            'created_by' => $admin->id,
        ]);

        $this->attempt($peserta, $remidi, 90);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertViewHas('leaderboard', function ($rows) use ($peserta) {
                $baris = collect($rows)->firstWhere('id', $peserta->id);

                return $baris !== null && (int) $baris->attempts_sum_skor_total === 90;
            })
            ->assertViewHas('stats', fn ($stats) => (int) $stats['total_skor'] === 90);
    }

    public function test_hasil_peserta_menampilkan_nilai_akhir_remidi(): void
    {
        [$admin, $kelas, $quiz] = $this->dasar(true);
        $peserta = $this->peserta('peserta-hasil');
        $awal = $this->sesi($quiz, $admin, 'acak', 'selesai');
        $attempt = $this->attempt($peserta, $awal, 50);

        QuizGrade::create([
            'organization_id' => $awal->organization_id,
            'sesi_awal_id' => $awal->id,
            'user_id' => $peserta->id,
            'nilai_awal' => 50,
            'nilai_remidi' => 80,
            'nilai_akhir' => 80,
            'sumber' => 'remidi',
            'ditetapkan_at' => now(),
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('Nilai akhir')
            ->assertSee('80');
    }

    private function dasar(bool $remidiAktif = false): array
    {
        $admin = User::create([
            'nama' => 'Admin Remidi',
            'username' => 'admin-remidi',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $kelas = Kelas::create([
            'nama' => 'TI-3A',
            'pin' => '1234',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Algoritma',
            'created_by' => $admin->id,
            'remidi_aktif' => $remidiAktif,
            'remidi_nilai_min' => $remidiAktif ? 70 : null,
        ]);

        return [$admin, $kelas, $quiz];
    }

    private function peserta(string $username): User
    {
        return User::create([
            'nama' => 'Peserta '.$username,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);
    }

    private function sesi(Quiz $quiz, User $admin, string $mode, string $status): QuizSession
    {
        return QuizSession::create([
            'quiz_id' => $quiz->id,
            'kelas_id' => $quiz->kelas_id,
            'nama' => 'Sesi '.$quiz->judul,
            'mode' => $mode,
            'status' => $status,
            'display_token' => 'token-'.uniqid(),
            'created_by' => $admin->id,
        ]);
    }

    private function attempt(User $peserta, QuizSession $session, int $nilai): Attempt
    {
        return Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => $nilai,
            'nilai' => $nilai,
        ]);
    }
}
