<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\AttemptSnapshot;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\OrganizationUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemoteExamAntiAiAndScreenProctoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_sesi_dan_batas_telat_menolak_peserta_yang_salah_pin_atau_terlambat(): void
    {
        [$org, $admin, $peserta, $quiz] = $this->buatDasar('alpha', 'peserta_pin');
        $this->tambahSoalPg($quiz, 'Soal PIN 1');

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Ujian Lab Terkunci PIN',
            'mode' => 'acak',
            'kode_akses' => 'LAB402',
            'batas_telat_menit' => 15,
            'status' => 'berjalan',
            'dibuka_at' => now()->subMinutes(5),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        // Salah PIN ditolak
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session), ['kode_akses' => 'SALAH9'])
            ->assertSessionHasErrors('kode_akses');

        $this->assertDatabaseMissing('attempts', [
            'quiz_session_id' => $session->id,
            'user_id' => $peserta->id,
        ]);

        // PIN benar diterima
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session), ['kode_akses' => 'lab402'])
            ->assertSessionHasNoErrors();

        // Jika sudah lewat batas telat 15 menit dan belum mulai, start ditolak
        $session->update(['dibuka_at' => now()->subMinutes(20)]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session))
            ->assertSessionHasErrors('attempt');
    }

    public function test_isolasi_satu_soal_aktif_dan_timer_per_soal_mencegah_intip_dan_kembali(): void
    {
        [$org, $admin, $peserta, $quiz] = $this->buatDasar('alpha', 'peserta_sekuensial');
        $q1 = $this->tambahSoalPg($quiz, 'Teks Rahasia Soal Nomor Satu', 45);
        $q2 = $this->tambahSoalPg($quiz, 'Teks Rahasia Soal Nomor Dua', 45);
        $q3 = $this->tambahSoalPg($quiz, 'Teks Rahasia Soal Nomor Tiga', 45);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Ujian Rumah Anti Foto AI',
            'mode' => 'acak',
            'navigasi_sekuensial' => true,
            'timer_per_soal' => true,
            'pisah_konteks_soal' => true,
            'watermark_layar' => true,
            'status' => 'berjalan',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session));

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();
        $orderedAqs = $attempt->attemptQuestions()->with('question')->orderBy('urutan')->get();
        $aq1 = $orderedAqs[0];
        $aq2 = $orderedAqs[1];
        $aq3 = $orderedAqs[2];

        // Halaman ujian hanya memuat teks soal aktif urutan 1, soal urutan 2 & 3 sama sekali tidak dikirim ke browser
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->get(route('peserta.attempt.show', $attempt))
            ->assertOk()
            ->assertSee($aq1->question->teks)
            ->assertDontSee($aq2->question->teks)
            ->assertDontSee($aq3->question->teks);

        // Mencoba menjawab soal urutan 2 sebelum dibuka ditolak 403
        $opsiAq2 = (int) $aq2->urutan_opsi_json[0];
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $aq2->question_id,
                'option_id' => $opsiAq2,
            ])
            ->assertStatus(403);

        // Lanjut ke soal 2 mengunci soal 1
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.next-question', $attempt))
            ->assertOk()
            ->assertJsonPath('posisi_terkunci_urutan', 2)
            ->assertJsonPath('question.id', $aq2->question_id);

        // Menjawab soal 1 yang sudah dikunci ditolak 403
        $opsiAq1 = (int) $aq1->urutan_opsi_json[0];
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $aq1->question_id,
                'option_id' => $opsiAq1,
            ])
            ->assertStatus(403);

        // Simulasikan timer per soal urutan 2 habis di server -> jawab ditolak 403
        $aq2->refresh()->update(['berakhir_at' => now()->subSeconds(3)->getTimestampMs()]);
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $aq2->question_id,
                'option_id' => $opsiAq2,
            ])
            ->assertStatus(403);

        unset($q1, $q2, $q3);
    }

    public function test_telemetri_ritme_ketikan_essay_tersimpan_dan_menandai_anomali_transkripsi(): void
    {
        [$org, $admin, $peserta, $quiz] = $this->buatDasar('alpha', 'peserta_essay');
        $essay = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan arsitektur client-server secara mendalam.',
            'tipe' => 'essay',
            'durasi_detik' => 120,
            'bobot' => 20,
        ]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'nama' => 'UTS Arsitektur Sistem',
            'mode' => 'acak',
            'status' => 'berjalan',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session));

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();

        $teksPanjang = str_repeat('Arsitektur client server memisahkan penyedia layanan dan peminta layanan melalui protokol jaringan. ', 2);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => $teksPanjang,
                'typing_metrics' => [
                    'first_input_delay_ms' => 52000,
                    'typing_duration_ms' => 14000,
                    'total_keys' => 195,
                    'backspace_keys' => 1,
                    'paste_events' => 0,
                    'idle_gaps' => 0,
                ],
            ])
            ->assertOk();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.finish', $attempt));

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('Indikasi salin perangkat lain');
    }

    public function test_tangkap_layar_hemat_server_intip_layar_dan_privasi_proyektor(): void
    {
        Storage::fake('local');

        [$orgAlpha, $adminAlpha, $pesertaAlpha, $quizAlpha] = $this->buatDasar('alpha', 'peserta_layar');
        [$orgBeta, $adminBeta] = $this->buatDasar('beta', 'peserta_beta');
        $this->tambahSoalPg($quizAlpha, 'Soal Pengawasan Layar');

        $session = QuizSession::create([
            'organization_id' => $orgAlpha->id,
            'quiz_id' => $quizAlpha->id,
            'nama' => 'UAS Keamanan Jaringan',
            'mode' => 'acak',
            'screen_proctoring' => 'desktop_only',
            'webcam_proctoring' => true,
            'max_pelanggaran' => 5,
            'durasi_peringatan' => 30,
            'status' => 'berjalan',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $adminAlpha->id,
        ]);

        $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->post(route('peserta.attempt.start', $session));

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $pesertaAlpha->id)->firstOrFail();

        // Isi 40 snapshot lama untuk menguji batas rotasi maksimal 40 screenshot layar per attempt
        for ($i = 1; $i <= 40; $i++) {
            $oldPath = "attempt-snapshots/{$orgAlpha->id}/{$attempt->id}/old-{$i}.webp";
            Storage::disk('local')->put($oldPath, 'webp-dummy-bytes');
            AttemptSnapshot::withoutGlobalScopes()->create([
                'organization_id' => $orgAlpha->id,
                'attempt_id' => $attempt->id,
                'question_urutan' => 1,
                'capture_source' => 'screen',
                'trigger_type' => 'tab_hidden',
                'path' => $oldPath,
                'size_bytes' => 16,
                'captured_at' => now()->subMinutes(50 - $i),
            ]);
        }

        $oldestPath = "attempt-snapshots/{$orgAlpha->id}/{$attempt->id}/old-1.webp";

        $fileBaru = UploadedFile::fake()->createWithContent('screen.webp', str_repeat('W', 1024));
        $uploadRes = $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->postJson(route('peserta.attempt.snapshot', $attempt), [
                'image' => $fileBaru,
                'capture_source' => 'screen',
                'trigger_type' => 'tab_hidden',
                'question_urutan' => 1,
            ])
            ->assertCreated();

        // Jumlah tetap tertahan di 40 dan file paling lama ikut terhapus
        $this->assertSame(
            40,
            AttemptSnapshot::withoutGlobalScopes()
                ->where('attempt_id', $attempt->id)
                ->where('capture_source', 'screen')
                ->count()
        );
        Storage::disk('local')->assertMissing($oldestPath);

        $snapshotTerbaru = AttemptSnapshot::withoutGlobalScopes()
            ->where('attempt_id', $attempt->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertGreaterThan(0, app(OrganizationUsage::class)->storageUsed($orgAlpha->id));

        // Admin organisasi sendiri dapat membuka gambar, sedangkan admin organisasi lain ditolak
        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.snapshots.show', $snapshotTerbaru))
            ->assertOk();

        $this->actingAs($adminBeta)
            ->withSession(['auth_token' => $adminBeta->current_session_token, 'organization_id' => $orgBeta->id])
            ->get(route('admin.snapshots.show', $snapshotTerbaru))
            ->assertNotFound();

        // Admin meminta Intip Layar 60 detik pada 1 peserta
        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->postJson(route('admin.sessions.attempts.peek-screen', [$session, $attempt]))
            ->assertOk();

        $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->getJson(route('peserta.attempt.status', $attempt))
            ->assertOk()
            ->assertJsonPath('screen_peek_active', true);

        // Catat pelanggaran keluar tab -> pastikan endpoint publik proyektor memuat status tanpa membocorkan URL gambar!
        $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->postJson(route('peserta.attempt.events', $attempt), [
                'tipe' => 'tab_hidden',
                'detail' => 'Berpindah tab',
            ])
            ->assertCreated();

        $displayResponse = $this->getJson(route('display.state', $session->display_token))
            ->assertOk()
            ->assertJsonPath('peringatan.0.bukti_layar_aktif', true)
            ->assertJsonPath('sedang_di_luar.0.nama', $pesertaAlpha->nama);

        $rawDisplayJson = (string) $displayResponse->getContent();
        $this->assertStringNotContainsString('/admin/snapshots', $rawDisplayJson);
        $this->assertStringNotContainsString('attempt-snapshots', $rawDisplayJson);

        // Pembersihan otomatis setelah >14 hari
        $snapshotTerbaru->update(['captured_at' => now()->subDays(15)]);
        $pathUsang = $snapshotTerbaru->path;
        $this->artisan('kuis:bersihkan-data-usang')->assertSuccessful();
        Storage::disk('local')->assertMissing($pathUsang);
    }

    private function buatDasar(string $slug, string $usernamePeserta): array
    {
        $org = Organization::create([
            'name' => 'Org '.ucfirst($slug),
            'slug' => 'org-'.$slug.'-'.Str::lower(Str::random(4)),
            'type' => 'education',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin '.ucfirst($slug),
            'username' => 'adm_'.$slug.'_'.Str::lower(Str::random(4)),
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'tok-adm-'.$slug,
        ]);

        $peserta = User::create([
            'nama' => 'Peserta '.ucfirst($slug),
            'username' => $usernamePeserta,
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'tok-'.$usernamePeserta,
        ]);

        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);
        $org->users()->attach($peserta->id, ['role' => 'participant', 'status' => 'active']);

        $quiz = Quiz::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'judul' => 'Kuis '.ucfirst($slug),
            'acak_opsi' => true,
            'created_by' => $admin->id,
        ]);

        return [$org, $admin, $peserta, $quiz];
    }

    private function tambahSoalPg(Quiz $quiz, string $teks, int $durasi = 30): Question
    {
        $q = Question::withoutGlobalScopes()->create([
            'organization_id' => $quiz->organization_id,
            'quiz_id' => $quiz->id,
            'teks' => $teks,
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => $durasi,
            'bobot' => 10,
        ]);

        $q->options()->createMany([
            ['teks' => 'Opsi Benar', 'is_correct' => true],
            ['teks' => 'Opsi Salah 1', 'is_correct' => false],
            ['teks' => 'Opsi Salah 2', 'is_correct' => false],
            ['teks' => 'Opsi Salah 3', 'is_correct' => false],
        ]);

        return $q;
    }
}
