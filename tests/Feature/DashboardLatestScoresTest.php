<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardLatestScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_admin_mengambil_nilai_tertinggi_per_peserta_dan_kuis(): void
    {
        $admin = $this->admin('admin_nilai_tertinggi');
        $kelas = $this->kelas($admin, 'Kelas Nilai', 'NILAI1');
        $quiz = $this->quiz($kelas, $admin, 'Kuis Nilai Tertinggi');
        $sesi = $this->sesi($quiz, $admin, 'Sesi Nilai Tertinggi', 'token-nilai-tertinggi');

        $data = [
            ['nama' => 'Peserta Tertua', 'username' => 'peserta_tertua', 'menit' => 7, 'nilai' => 95],
            ['nama' => 'Peserta Baru Lima', 'username' => 'peserta_baru_lima', 'menit' => 6, 'nilai' => 10],
            ['nama' => 'Peserta Baru Empat', 'username' => 'peserta_baru_empat', 'menit' => 5, 'nilai' => 20],
            ['nama' => 'Peserta Tiga', 'username' => 'peserta_tiga', 'menit' => 4, 'nilai' => 60],
            ['nama' => 'Peserta Dua', 'username' => 'peserta_dua', 'menit' => 3, 'nilai' => 70],
            ['nama' => 'Peserta Satu', 'username' => 'peserta_satu', 'menit' => 2, 'nilai' => 80],
            ['nama' => 'Peserta Terbaru', 'username' => 'peserta_terbaru', 'menit' => 1, 'nilai' => 90],
        ];

        foreach ($data as $baris) {
            $peserta = $this->peserta($baris['nama'], $baris['username']);
            $this->attempt($peserta, $sesi, $baris['nilai'], $baris['menit']);
        }

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('leaderboardTerbaru', function ($leaderboard) {
            return $leaderboard->map(fn ($baris) => (int) $baris->nilai)->all() === [95, 90, 80, 70, 60]
                && $leaderboard->pluck('nama')->all() === [
                    'Peserta Tertua',
                    'Peserta Terbaru',
                    'Peserta Satu',
                    'Peserta Dua',
                    'Peserta Tiga',
                ];
        });
        $response->assertSee('Nilai Tertinggi');
        $response->assertSee('Nilai 95');
    }

    public function test_dashboard_admin_memfilter_nilai_berdasarkan_kelas_dan_kuis(): void
    {
        $admin = $this->admin('admin_nilai_filter');
        $kelasA = $this->kelas($admin, 'Kelas Filter A', 'FILTA1');
        $kelasB = $this->kelas($admin, 'Kelas Filter B', 'FILTB1');
        $quizA = $this->quiz($kelasA, $admin, 'Kuis Filter A');
        $quizB = $this->quiz($kelasB, $admin, 'Kuis Filter B');
        $sesiA = $this->sesi($quizA, $admin, 'Sesi Filter A', 'token-filter-a');
        $sesiB = $this->sesi($quizB, $admin, 'Sesi Filter B', 'token-filter-b');

        $pesertaA = $this->peserta('Peserta A', 'peserta_filter_a');
        $pesertaB = $this->peserta('Peserta B', 'peserta_filter_b');

        $this->attempt($pesertaA, $sesiA, 70, 3);
        $this->attempt($pesertaB, $sesiB, 80, 2);
        $this->attempt($pesertaA, $sesiB, 60, 1);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard', ['kelas' => $kelasA->id]))
            ->assertOk()
            ->assertViewHas('filterKelasId', $kelasA->id)
            ->assertViewHas('leaderboardTerbaru', fn ($leaderboard) => $leaderboard
                ->map(fn ($baris) => [$baris->nama, (int) $baris->nilai])
                ->all() === [['Peserta A', 70]])
            ->assertViewHas('opsiKelas', fn ($opsi) => collect($opsi)->pluck('kelas_nama')->sort()->values()->all() === [
                'Kelas Filter A',
                'Kelas Filter B',
            ])
            ->assertViewHas('opsiKuis', fn ($opsi) => collect($opsi)->pluck('kuis_judul')->sort()->values()->all() === [
                'Kuis Filter A',
                'Kuis Filter B',
            ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard', ['kuis' => $quizB->id]))
            ->assertOk()
            ->assertViewHas('filterKuisId', $quizB->id)
            ->assertViewHas('leaderboardTerbaru', fn ($leaderboard) => $leaderboard
                ->map(fn ($baris) => [$baris->nama, (int) $baris->nilai])
                ->all() === [['Peserta B', 80], ['Peserta A', 60]]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard', ['kelas' => $kelasA->id, 'kuis' => $quizB->id]))
            ->assertOk()
            ->assertViewHas('leaderboardTerbaru', fn ($leaderboard) => $leaderboard->isEmpty());
    }

    public function test_dashboard_admin_hanya_menampilkan_satu_nilai_terbaik_per_peserta_per_kuis(): void
    {
        $admin = $this->admin('admin_nilai_dedupe');
        $kelas = $this->kelas($admin, 'Kelas Dedupe', 'DEDUPE1');
        $quiz = $this->quiz($kelas, $admin, 'Kuis Dedupe');
        $sesiUtama = $this->sesi($quiz, $admin, 'Sesi Utama', 'token-dedupe-utama');
        $sesiUlang = $this->sesi($quiz, $admin, 'Sesi Ulang', 'token-dedupe-ulang');
        $quizRemidi = $this->quiz($kelas, $admin, 'Kuis Remidi Dedupe');
        $sesiRemidi = $this->sesi($quizRemidi, $admin, 'Sesi Remidi Dedupe', 'token-dedupe-remidi', [
            'jenis' => 'remidi',
            'remidi_dari_session_id' => $sesiUtama->id,
            'remidi_soal' => 'baru',
        ]);

        $pesertaSatu = $this->peserta('Peserta Satu', 'peserta_dedupe_satu');
        $pesertaDua = $this->peserta('Peserta Dua', 'peserta_dedupe_dua');

        $this->attempt($pesertaSatu, $sesiUtama, 90, 20);
        $this->attempt($pesertaSatu, $sesiUlang, 40, 15);
        $this->attempt($pesertaSatu, $sesiRemidi, 50, 10);
        $this->attempt($pesertaDua, $sesiUtama, 90, 1);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('leaderboardTerbaru', fn ($leaderboard) => $leaderboard
                ->map(fn ($baris) => [$baris->nama, (int) $baris->nilai])
                ->all() === [['Peserta Dua', 90], ['Peserta Satu', 90]]);
    }

    private function admin(string $username): User
    {
        return User::create([
            'nama' => 'Admin '.$username,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => $username.'-token',
        ]);
    }

    private function kelas(User $admin, string $nama, string $pin): Kelas
    {
        return Kelas::create([
            'nama' => $nama,
            'pin' => $pin,
            'created_by' => $admin->id,
        ]);
    }

    private function quiz(Kelas $kelas, User $admin, string $judul): Quiz
    {
        return Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => $judul,
            'created_by' => $admin->id,
            'is_percobaan' => false,
        ]);
    }

    private function sesi(Quiz $quiz, User $admin, string $nama, string $token, array $atribut = []): QuizSession
    {
        return QuizSession::create(array_merge([
            'quiz_id' => $quiz->id,
            'kelas_id' => $quiz->kelas_id,
            'nama' => $nama,
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => $token,
            'created_by' => $admin->id,
        ], $atribut));
    }

    private function peserta(string $nama, string $username): User
    {
        return User::create([
            'nama' => $nama,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'peserta',
        ]);
    }

    private function attempt(User $peserta, QuizSession $sesi, int $nilai, int $menit): Attempt
    {
        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $sesi->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'selesai_at' => now()->getTimestampMs(),
            'skor_total' => $nilai,
            'nilai' => $nilai,
        ]);

        DB::table('attempts')->where('id', $attempt->id)->update([
            'updated_at' => now()->subMinutes($menit),
        ]);

        return $attempt;
    }
}
