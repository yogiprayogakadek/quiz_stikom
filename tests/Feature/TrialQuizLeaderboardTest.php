<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionQuestion;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TrialQuizLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menandai_kuis_percobaan(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['nama' => 'TI-1A', 'pin' => '1234', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Latihan Awal',
                'is_percobaan' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue(Quiz::where('judul', 'Kuis Latihan Awal')->firstOrFail()->is_percobaan);
    }

    public function test_seeder_menandai_kuis_uji_coba_sebagai_percobaan(): void
    {
        $this->admin();

        $this->seed(\Database\Seeders\TrialQuizSeeder::class);

        $this->assertTrue(Quiz::where('judul', 'Kuis Uji Coba')->firstOrFail()->is_percobaan);
    }

    public function test_kuis_percobaan_tidak_masuk_leaderboard_sesi(): void
    {
        $admin = $this->admin();
        $peserta = $this->peserta('peserta-satu');

        $quiz = Quiz::create([
            'judul' => 'Kuis Uji Coba',
            'is_percobaan' => true,
            'created_by' => $admin->id,
        ]);

        $session = $this->buatSesi($quiz, $admin, 'selesai', 'acak');

        Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => 90,
            'nilai' => 90,
        ]);

        $this->assertSame([], app(QuizEngine::class)->leaderboard($session));
    }

    public function test_leaderboard_sesi_mengurutkan_berdasarkan_nilai(): void
    {
        $admin = $this->admin();
        $rendah = $this->peserta('peserta-rendah');
        $tinggi = $this->peserta('peserta-tinggi');

        $quiz = Quiz::create(['judul' => 'Kuis Serempak', 'created_by' => $admin->id]);
        $session = $this->buatSesi($quiz, $admin, 'selesai', 'serempak');

        Attempt::create([
            'user_id' => $rendah->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => 1500,
            'nilai' => 75,
        ]);

        Attempt::create([
            'user_id' => $tinggi->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'skor_total' => 800,
            'nilai' => 80,
        ]);

        $baris = app(QuizEngine::class)->leaderboard($session);

        $this->assertSame(80, $baris[0]['nilai']);
        $this->assertSame('Peserta peserta-tinggi', $baris[0]['nama']);
    }

    public function test_hitung_ulang_serempak_mengisi_nilai_ternormalisasi(): void
    {
        $admin = $this->admin();
        $peserta = $this->peserta('peserta-serempak');

        $quiz = Quiz::create(['judul' => 'Kuis Serempak Dua Soal', 'created_by' => $admin->id]);
        $session = $this->buatSesi($quiz, $admin, 'selesai', 'serempak');

        foreach ([1, 2] as $urutan) {
            $soal = Question::create([
                'quiz_id' => $quiz->id,
                'teks' => 'Soal '.$urutan,
                'tipe' => 'pilihan_ganda',
                'durasi_detik' => 30,
                'bobot' => 1,
            ]);

            $soal->options()->create([
                'teks' => 'Opsi '.$urutan,
                'is_correct' => true,
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
            'status' => 'berjalan',
            'bergabung_at' => now(),
            'skor_total' => 1500,
        ]);

        $questions = $session->sessionQuestions()->with('question.options')->get();

        foreach ($questions as $index => $sessionQuestion) {
            \App\Models\Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $sessionQuestion->question_id,
                'option_id' => $sessionQuestion->question->options->first()->id,
                'skor' => $index === 0 ? 1000 : 500,
                'answered_at' => now(),
            ]);
        }

        app(QuizEngine::class)->hitungUlangSkorSerempak($attempt->id);

        $this->assertSame(75, $attempt->fresh()->nilai);
    }

    public function test_dashboard_peserta_memakai_nilai_terbaik_per_kuis(): void
    {
        $admin = $this->admin();
        $peserta = $this->peserta('peserta-dashboard');

        $quiz = Quiz::create(['judul' => 'Kuis Utama', 'created_by' => $admin->id]);
        $sesiPertama = $this->buatSesi($quiz, $admin, 'selesai', 'acak', 'Sesi Pertama');
        $sesiKedua = $this->buatSesi($quiz, $admin, 'selesai', 'acak', 'Sesi Kedua');

        $percobaan = Quiz::create([
            'judul' => 'Kuis Percobaan',
            'is_percobaan' => true,
            'created_by' => $admin->id,
        ]);
        $sesiPercobaan = $this->buatSesi($percobaan, $admin, 'selesai', 'acak', 'Sesi Percobaan');

        foreach ([[$sesiPertama, 60], [$sesiKedua, 90], [$sesiPercobaan, 100]] as [$sesi, $nilai]) {
            Attempt::create([
                'user_id' => $peserta->id,
                'quiz_session_id' => $sesi->id,
                'status' => 'selesai',
                'bergabung_at' => now(),
                'skor_total' => $nilai,
                'nilai' => $nilai,
            ]);
        }

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

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin',
            'username' => 'admin-percobaan',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);
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

    private function buatSesi(Quiz $quiz, User $admin, string $status, string $mode, ?string $nama = null): QuizSession
    {
        return QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => $nama ?? 'Sesi '.$quiz->judul,
            'mode' => $mode,
            'status' => $status,
            'display_token' => 'token-'.uniqid(),
            'created_by' => $admin->id,
        ]);
    }
}
