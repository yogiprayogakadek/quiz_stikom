<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportAndQuizOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_mengakses_halaman_berita_acara(): void
    {
        $admin = User::create([
            'nama' => 'Admin Penguji',
            'username' => 'admin_test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $kelas = Kelas::create([
            'nama' => 'TI-3A',
            'pin' => '1234',
        ]);

        $quiz = Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Ujian Algoritma',
            'jumlah_soal' => 10,
            'acak_opsi' => true,
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi UAS TI-3A',
            'mode' => 'acak',
            'acak_opsi' => true,
            'status' => 'selesai',
            'display_token' => 'token-sesi-123',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.reports.berita-acara', $session));

        $response->assertOk();
        $response->assertSee('BERITA ACARA PELAKSANAAN UJIAN');
        $response->assertSee('Sesi UAS TI-3A');
        $response->assertSee('Ujian Algoritma');
    }

    public function test_admin_bisa_membuat_kuis_dengan_acak_opsi(): void
    {
        $admin = User::create([
            'nama' => 'Admin Penguji 2',
            'username' => 'admin_test2',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin2',
        ]);

        $kelas = Kelas::create([
            'nama' => 'SI-2B',
            'pin' => '1234',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin2'])
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Basis Data',
                'acak_opsi' => 1,
            ]);

        $response->assertRedirect();
        $quiz = Quiz::where('judul', 'Kuis Basis Data')->first();
        $this->assertNotNull($quiz);
        $this->assertTrue($quiz->acak_opsi);
    }

    public function test_admin_bisa_ekspor_bank_soal_ke_csv(): void
    {
        $admin = User::create([
            'nama' => 'Admin Soal',
            'username' => 'admin_soal',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-soal',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Jaringan Komputer',
            'jumlah_soal' => 1,
            'created_by' => $admin->id,
        ]);

        $soal = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Apa kepanjangan IP?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 2,
            'durasi_detik' => 30,
        ]);

        Option::create([
            'question_id' => $soal->id,
            'teks' => 'Internet Protocol',
            'is_correct' => true,
        ]);
        Option::create([
            'question_id' => $soal->id,
            'teks' => 'Internal Port',
            'is_correct' => false,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-soal'])
            ->get(route('admin.quizzes.questions.export', $quiz));

        $response->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Apa kepanjangan IP?', $content);
        $this->assertStringContainsString('Internet Protocol', $content);
    }

    public function test_admin_bisa_menambah_waktu_peserta_dan_reset_attempt(): void
    {
        $admin = User::create([
            'nama' => 'Admin Sesi',
            'username' => 'admin_sesi',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-sesi',
        ]);

        $peserta = User::create([
            'nama' => 'Ahmad Santoso',
            'username' => 'ahmad123',
            'password' => Hash::make('password'),
            'role' => 'peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Pemrograman Web',
            'jumlah_soal' => 5,
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'UAS Web',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'display-token-web',
            'created_by' => $admin->id,
        ]);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'berjalan',
            'batas_akhir_at' => (int) (microtime(true) * 1000) + (10 * 60 * 1000),
        ]);

        // 1. Tambah Waktu
        $resWaktu = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-sesi'])
            ->post(route('admin.sessions.attempts.add-time', [$session, $attempt]), [
                'minutes' => 15,
            ]);

        $resWaktu->assertRedirect();
        $attempt->refresh();
        $this->assertGreaterThan((int) (microtime(true) * 1000) + (20 * 60 * 1000), $attempt->batas_akhir_at);

        // 2. Reset Attempt
        $resReset = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-sesi'])
            ->post(route('admin.sessions.attempts.reset', [$session, $attempt]));

        $resReset->assertRedirect();
        $this->assertDatabaseMissing('attempts', ['id' => $attempt->id]);
    }

    public function test_admin_bisa_ekspor_rekap_nilai_csv(): void
    {
        $admin = User::create([
            'nama' => 'Admin Nilai',
            'username' => 'admin_nilai',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-nilai',
        ]);

        $peserta = User::create([
            'nama' => 'Citra Lestari',
            'username' => 'citra123',
            'password' => Hash::make('password'),
            'role' => 'peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Rekap Nilai',
            'jumlah_soal' => 2,
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Rekap',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-rekap',
            'created_by' => $admin->id,
        ]);

        Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'jumlah_benar' => 2,
            'poin_total' => 10,
            'poin_maksimal' => 10,
            'skor_total' => 100,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-nilai'])
            ->get(route('admin.reports.export', $session));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Citra Lestari', $content);
        $this->assertStringContainsString('citra123', $content);
        $this->assertStringContainsString('100', $content);
    }

    public function test_laporan_menampilkan_daya_beda_dan_rata_rata_waktu_soal(): void
    {
        $admin = User::create([
            'nama' => 'Admin Analisis',
            'username' => 'admin_analisis',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-analisis',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Analisis Butir',
            'jumlah_soal' => 1,
            'created_by' => $admin->id,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal untuk analisis butir',
            'tipe' => 'pilihan_ganda',
            'bobot' => 1,
            'durasi_detik' => 60,
        ]);

        $benar = Option::create([
            'question_id' => $question->id,
            'teks' => 'Jawaban benar',
            'is_correct' => true,
        ]);

        $salah = Option::create([
            'question_id' => $question->id,
            'teks' => 'Jawaban salah',
            'is_correct' => false,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Analisis',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-analisis-sesi',
            'created_by' => $admin->id,
        ]);

        foreach (range(1, 6) as $nomor) {
            $peserta = User::create([
                'nama' => 'Peserta Analisis '.$nomor,
                'username' => 'analisis-'.$nomor,
                'password' => Hash::make('password'),
                'role' => 'peserta',
            ]);

            $attempt = Attempt::create([
                'user_id' => $peserta->id,
                'quiz_session_id' => $session->id,
                'status' => 'selesai',
                'bergabung_at' => now(),
                'skor_total' => $nomor * 10,
            ]);

            AttemptQuestion::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'urutan' => 1,
                'urutan_opsi_json' => [$benar->id, $salah->id],
            ]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'option_id' => $nomor >= 4 ? $benar->id : $salah->id,
                'waktu_jawab_ms' => $nomor * 1000,
                'skor' => 0,
                'answered_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-analisis'])
            ->get(route('admin.reports.session', $session));

        $response->assertOk();
        $response->assertSee('Analisis butir soal pilihan ganda');
        $response->assertSee('Rata-rata waktu');
        $response->assertSee('Daya beda');
        $response->assertSee('1.00');
        $response->assertSee('4 dtk');
    }
}
