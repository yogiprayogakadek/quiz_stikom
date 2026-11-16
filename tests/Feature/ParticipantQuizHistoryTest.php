<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParticipantQuizHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_hanya_melihat_attempt_miliknya_di_workspace_aktif(): void
    {
        [$organization, $admin, $peserta, $lain] = $this->organisasi('riwayat_alpha');

        $milikSendiri = $this->sesi($organization, $admin, 'Riwayat Milik Sendiri');
        $this->attempt($peserta, $milikSendiri);

        $milikLain = $this->sesi($organization, $admin, 'Riwayat Orang Lain');
        $this->attempt($lain, $milikLain);

        [$organisasiLain, $adminLain] = $this->organisasi('riwayat_beta');
        $workspaceLain = $this->sesi($organisasiLain, $adminLain, 'Riwayat Workspace Lain');
        $this->attempt($peserta, $workspaceLain);

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->assertSee('Riwayat Milik Sendiri')
            ->assertDontSee('Riwayat Orang Lain')
            ->assertDontSee('Riwayat Workspace Lain');
    }

    public function test_attempt_menunggu_berjalan_dan_hanya_disiapkan_admin_tidak_muncul(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_status');

        $selesai = $this->sesi($organization, $admin, 'Riwayat Sudah Selesai');
        $this->attempt($peserta, $selesai, ['status' => 'selesai']);

        $berjalan = $this->sesi($organization, $admin, 'Riwayat Masih Berjalan');
        $this->attempt($peserta, $berjalan, ['status' => 'berjalan', 'selesai_at' => null]);

        $disiapkan = $this->sesi($organization, $admin, 'Riwayat Disiapkan Admin');
        $this->attempt($peserta, $disiapkan, [
            'status' => 'menunggu',
            'mulai_at' => null,
            'selesai_at' => null,
            'bergabung_at' => null,
        ]);

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->assertSee('Riwayat Sudah Selesai')
            ->assertDontSee('Riwayat Masih Berjalan')
            ->assertDontSee('Riwayat Disiapkan Admin');
    }

    public function test_default_hanya_ujian_nyata_dan_filter_jenis_bekerja(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_jenis');

        $this->attempt($peserta, $this->sesi($organization, $admin, 'Riwayat Ujian Nyata'));
        $this->attempt($peserta, $this->sesi($organization, $admin, 'Riwayat Sesi Simulasi', ['percobaan' => true]));
        $this->attempt($peserta, $this->sesi($organization, $admin, 'Riwayat Remidi Cerdas', ['jenis' => 'remidi']));

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->assertSee('Riwayat Ujian Nyata')
            ->assertSee('Riwayat Remidi Cerdas')
            ->assertDontSee('Riwayat Sesi Simulasi');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['jenis' => 'simulasi']))
            ->assertOk()
            ->assertSee('Riwayat Sesi Simulasi')
            ->assertDontSee('Riwayat Ujian Nyata')
            ->assertDontSee('Riwayat Remidi Cerdas');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['jenis' => 'semua']))
            ->assertOk()
            ->assertSee('Riwayat Ujian Nyata')
            ->assertSee('Riwayat Sesi Simulasi')
            ->assertSee('Riwayat Remidi Cerdas');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['jenis' => 'tidak-dikenal']))
            ->assertOk()
            ->assertSee('Riwayat Ujian Nyata')
            ->assertDontSee('Riwayat Sesi Simulasi');
    }

    public function test_filter_status_membedakan_essay_tertunda_dan_final(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_nilai');

        $tertunda = $this->sesi($organization, $admin, 'Riwayat Menunggu Nilai');
        $attemptTertunda = $this->attempt($peserta, $tertunda, ['skor_total' => 85]);
        $this->jawabanEssay($attemptTertunda, 'Jawaban yang belum dinilai');

        $final = $this->sesi($organization, $admin, 'Riwayat Sudah Final');
        $attemptFinal = $this->attempt($peserta, $final, ['skor_total' => 0]);
        $this->jawabanEssay($attemptFinal, 'Jawaban yang sudah dinilai', now());

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Riwayat Menunggu Nilai')
            ->assertSee('Menunggu penilaian essay')
            ->assertSee('1 jawaban menunggu dinilai.')
            ->assertDontSee('Riwayat Sudah Final')
            ->assertDontSee('data-nilai-akhir', false);

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['status' => 'final']))
            ->assertOk()
            ->assertSee('Riwayat Sudah Final')
            ->assertDontSee('Riwayat Menunggu Nilai')
            ->assertSee('data-nilai-akhir="0"', false)
            ->assertDontSee('Menunggu penilaian essay');
    }

    public function test_jawaban_essay_kosong_tidak_dihitung_menunggu_penilaian(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_kosong');

        $sesi = $this->sesi($organization, $admin, 'Riwayat Essay Kosong');
        $attempt = $this->attempt($peserta, $sesi, ['skor_total' => 70]);
        $this->jawabanEssay($attempt, '');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['status' => 'final']))
            ->assertOk()
            ->assertSee('Riwayat Essay Kosong')
            ->assertSee('data-nilai-akhir="70"', false)
            ->assertDontSee('Menunggu penilaian essay');
    }

    public function test_filter_kelas_dan_pencarian_bekerja_dan_dapat_digabung(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_filter');

        $kelasPagi = $this->kelas($organization, $admin, 'Kelas Pagi');
        $kelasSore = $this->kelas($organization, $admin, 'Kelas Sore');

        $this->attempt($peserta, $this->sesi($organization, $admin, 'Sesi Sejarah Pagi', [
            'kuis' => 'Kuis Sejarah',
            'kelas' => $kelasPagi,
        ]));
        $this->attempt($peserta, $this->sesi($organization, $admin, 'Sesi Biologi Sore', [
            'kuis' => 'Kuis Biologi',
            'kelas' => $kelasSore,
        ]));

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['kelas' => $kelasPagi->id]))
            ->assertOk()
            ->assertSee('Sesi Sejarah Pagi')
            ->assertDontSee('Sesi Biologi Sore');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['kelas' => 99999]))
            ->assertOk()
            ->assertSee('Sesi Sejarah Pagi')
            ->assertSee('Sesi Biologi Sore');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['q' => 'Biologi']))
            ->assertOk()
            ->assertSee('Sesi Biologi Sore')
            ->assertDontSee('Sesi Sejarah Pagi');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['q' => 'Sejarah Pagi']))
            ->assertOk()
            ->assertSee('Sesi Sejarah Pagi')
            ->assertDontSee('Sesi Biologi Sore');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['q' => 'Kelas Sore']))
            ->assertOk()
            ->assertSee('Sesi Biologi Sore')
            ->assertDontSee('Sesi Sejarah Pagi');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', [
                'q' => 'Sesi',
                'kelas' => $kelasSore->id,
                'jenis' => 'real',
                'status' => 'final',
            ]))
            ->assertOk()
            ->assertSee('Sesi Biologi Sore')
            ->assertDontSee('Sesi Sejarah Pagi');
    }

    public function test_setiap_attempt_dari_kuis_yang_sama_tampil_terpisah(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_terpisah');

        $kuis = $this->kuis($organization, $admin, 'Kuis Ulangan Harian');
        $this->attempt($peserta, $this->sesi($organization, $admin, 'Sesi Ulangan Pertama', ['kuis_model' => $kuis]));
        $this->attempt($peserta, $this->sesi($organization, $admin, 'Sesi Ulangan Kedua', ['kuis_model' => $kuis]));

        $response = $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->assertSee('Sesi Ulangan Pertama')
            ->assertSee('Sesi Ulangan Kedua');

        $this->assertSame(2, substr_count($response->getContent(), 'data-riwayat-attempt='));
    }

    public function test_badge_dan_link_hasil_mengikuti_jenis_dan_mode(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_badge');

        $remidi = $this->sesi($organization, $admin, 'Sesi Remidi Akhir', ['jenis' => 'remidi', 'mode' => 'acak']);
        $attemptRemidi = $this->attempt($peserta, $remidi);

        $simulasi = $this->sesi($organization, $admin, 'Sesi Simulasi Serempak', ['percobaan' => true, 'mode' => 'serempak']);
        $attemptSimulasi = $this->attempt($peserta, $simulasi);

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['jenis' => 'semua']))
            ->assertOk()
            ->assertSee('Remidi')
            ->assertSee('Simulasi')
            ->assertSee('Ujian nyata')
            ->assertSee('Mode Acak')
            ->assertSee('Mode Serempak')
            ->assertSee('Tanpa kelas')
            ->assertSee(route('peserta.attempt.result', $attemptRemidi), false)
            ->assertSee(route('peserta.session.result', $simulasi), false)
            ->assertSee('Lihat hasil');
    }

    public function test_pagination_mempertahankan_query_string(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_halaman');

        foreach (range(1, 13) as $urutan) {
            $sesi = $this->sesi($organization, $admin, 'Riwayat Panjang '.$urutan, ['percobaan' => true]);
            $attempt = $this->attempt($peserta, $sesi);
            $this->jawabanEssay($attempt, 'Jawaban '.$urutan, now());
        }

        $response = $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', [
                'jenis' => 'simulasi',
                'status' => 'final',
                'q' => 'Riwayat Panjang',
            ]))
            ->assertOk();

        $this->assertSame(12, substr_count($response->getContent(), 'data-riwayat-attempt='));

        $response->assertSee(e(route('peserta.history.index', [
            'jenis' => 'simulasi',
            'status' => 'final',
            'q' => 'Riwayat Panjang',
            'page' => 2,
        ])), false);
    }

    public function test_empty_state_membedakan_belum_ada_riwayat_dan_filter_kosong(): void
    {
        [$organization, $admin, $peserta] = $this->organisasi('riwayat_kosong_total');

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->assertSee('Belum ada riwayat kuis')
            ->assertSee('Hasil akan muncul di sini setelah kamu menyelesaikan ujian.');

        $this->attempt($peserta, $this->sesi($organization, $admin, 'Riwayat Ujian Nyata'));

        $this->actingAs($peserta)
            ->withSession($this->sesiAuth($organization, $peserta))
            ->get(route('peserta.history.index', ['jenis' => 'simulasi']))
            ->assertOk()
            ->assertSee('Tidak ada riwayat pada filter ini')
            ->assertSee('Simulasi')
            ->assertSee('Reset filter');
    }

    public function test_admin_dan_pengguna_tanpa_membership_tidak_dapat_membuka_riwayat(): void
    {
        [$organization, $admin] = $this->organisasi('riwayat_akses');
        $tanpaMembership = $this->user('Peserta Lepas', 'peserta_lepas', 'peserta');

        $this->actingAs($admin)
            ->withSession($this->sesiAuth($organization, $admin))
            ->get(route('peserta.history.index'))
            ->assertForbidden();

        $this->actingAs($tanpaMembership)
            ->withSession([
                'auth_token' => $tanpaMembership->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->get(route('peserta.history.index'))
            ->assertForbidden();
    }

    private function organisasi(string $slug): array
    {
        $organization = Organization::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = $this->user('Admin '.$slug, 'admin_'.$slug, 'admin');
        $peserta = $this->user('Peserta '.$slug, 'peserta_'.$slug, 'peserta');
        $lain = $this->user('Lain '.$slug, 'lain_'.$slug, 'peserta');

        $organization->users()->attach([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
            $peserta->id => ['role' => 'participant', 'status' => 'active'],
            $lain->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        return [$organization, $admin, $peserta, $lain];
    }

    private function user(string $nama, string $username, string $role): User
    {
        return User::create([
            'nama' => $nama,
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);
    }

    private function sesiAuth(Organization $organization, User $user): array
    {
        return [
            'auth_token' => $user->current_session_token,
            'organization_id' => $organization->id,
        ];
    }

    private function kelas(Organization $organization, User $admin, string $nama): Kelas
    {
        return Kelas::create([
            'organization_id' => $organization->id,
            'nama' => $nama,
            'pin' => strtoupper(Str::random(6)),
            'created_by' => $admin->id,
        ]);
    }

    private function kuis(Organization $organization, User $admin, string $judul, bool $percobaan = false): Quiz
    {
        return Quiz::create([
            'organization_id' => $organization->id,
            'judul' => $judul,
            'is_percobaan' => $percobaan,
            'created_by' => $admin->id,
        ]);
    }

    private function sesi(Organization $organization, User $admin, string $nama, array $opsi = []): QuizSession
    {
        $kuis = $opsi['kuis_model'] ?? $this->kuis($organization, $admin, $opsi['kuis'] ?? $nama, $opsi['percobaan'] ?? false);
        $kelas = $opsi['kelas'] ?? null;

        return QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $kuis->id,
            'kelas_id' => $kelas?->id,
            'nama' => $nama,
            'mode' => $opsi['mode'] ?? 'acak',
            'jenis' => $opsi['jenis'] ?? 'utama',
            'status' => 'selesai',
            'display_token' => Str::random(40),
            'created_by' => $admin->id,
        ]);
    }

    private function attempt(User $user, QuizSession $session, array $opsi = []): Attempt
    {
        return Attempt::create([
            'user_id' => $user->id,
            'organization_id' => $session->organization_id,
            'quiz_session_id' => $session->id,
            'status' => $opsi['status'] ?? 'selesai',
            'mulai_at' => array_key_exists('mulai_at', $opsi) ? $opsi['mulai_at'] : now()->subMinutes(20)->getTimestampMs(),
            'selesai_at' => array_key_exists('selesai_at', $opsi) ? $opsi['selesai_at'] : now()->getTimestampMs(),
            'bergabung_at' => array_key_exists('bergabung_at', $opsi) ? $opsi['bergabung_at'] : now()->subMinutes(25),
            'skor_total' => $opsi['skor_total'] ?? 80,
        ]);
    }

    private function jawabanEssay(Attempt $attempt, string $jawaban, ?object $dinilaiAt = null): Answer
    {
        $session = $attempt->quizSession;
        $question = Question::create([
            'organization_id' => $attempt->organization_id,
            'quiz_id' => $session->quiz_id,
            'teks' => 'Jelaskan hasil pengamatanmu.',
            'tipe' => 'essay',
            'durasi_detik' => 300,
            'bobot' => 10,
        ]);

        return Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'jawaban_teks' => $jawaban,
            'answered_at' => now(),
            'dinilai_at' => $dinilaiAt,
        ]);
    }
}
