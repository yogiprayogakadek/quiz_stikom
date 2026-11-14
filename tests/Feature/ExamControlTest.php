<?php

namespace Tests\Feature;

use App\Events\PesertaWaktuDitambah;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExamControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_menyimpan_batas_dan_durasi_peringatan(): void
    {
        [$organization, $admin, , $session] = $this->dasar('pengaturan');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $session->quiz_id,
                'nama' => 'Sesi Pengaturan Baru',
                'mode' => 'acak',
                'jeda_detik' => 5,
                'max_pelanggaran' => 4,
                'durasi_peringatan' => 45,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_sessions', [
            'nama' => 'Sesi Pengaturan Baru',
            'max_pelanggaran' => 4,
            'durasi_peringatan' => 45,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $session->quiz_id,
                'nama' => 'Sesi Tanpa Batas',
                'mode' => 'acak',
                'jeda_detik' => 5,
                'max_pelanggaran' => null,
                'durasi_peringatan' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_sessions', [
            'nama' => 'Sesi Tanpa Batas',
            'max_pelanggaran' => null,
            'durasi_peringatan' => 30,
        ]);
    }

    public function test_satu_sesi_peringatan_hanya_menambah_satu_pelanggaran(): void
    {
        [$organization, $admin, $participant, $session, $attempt] = $this->dasar('pelanggaran', maxPelanggaran: 2);

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.events', $attempt), ['tipe' => 'blur_window'])
            ->assertCreated()
            ->assertJsonPath('jumlah', 1);

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.events', $attempt), ['tipe' => 'tab_hidden'])
            ->assertCreated()
            ->assertJsonPath('jumlah', 1);

        $this->assertSame(1, $attempt->fresh()->jumlah_pelanggaran);
        $this->assertSame(2, $attempt->events()->count());

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.events', $attempt), [
                'tipe' => 'kembali',
                'durasi_detik' => 3,
            ])
            ->assertOk();

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.events', $attempt), ['tipe' => 'fullscreen_exit'])
            ->assertCreated()
            ->assertJsonPath('jumlah', 2);

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.pelanggaran', $attempt))
            ->assertOk()
            ->assertJsonPath('final', true);

        $this->assertSame('selesai', $attempt->fresh()->status);
    }

    public function test_timeout_di_bawah_batas_tidak_mengumpulkan_ujian(): void
    {
        [$organization, , $participant, $session, $attempt] = $this->dasar('masih_bisa', maxPelanggaran: 3);

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.events', $attempt), ['tipe' => 'fullscreen_exit'])
            ->assertCreated();

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.pelanggaran', $attempt))
            ->assertOk()
            ->assertJsonPath('final', false);

        $attempt->refresh();
        $this->assertSame('berjalan', $attempt->status);
        $this->assertNull($attempt->peringatan_mulai_at);
    }

    public function test_tambah_waktu_bulk_memperbarui_timer_peserta_terpilih(): void
    {
        Event::fake([PesertaWaktuDitambah::class]);

        [$organization, $admin, $pesertaSatu, $session, $attemptSatu] = $this->dasar('waktu_satu');
        $pesertaDua = $this->peserta($organization, 'waktu_dua');
        $attemptDua = $this->attempt($session, $pesertaDua);

        $batasSatu = $attemptSatu->batas_akhir_at;
        $batasDua = $attemptDua->batas_akhir_at;

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.attempts.add-time-bulk', $session), [
                'attempt_ids' => [$attemptSatu->id, $attemptDua->id],
                'minutes' => 7,
            ])
            ->assertRedirect();

        $this->assertGreaterThan($batasSatu + (6 * 60 * 1000), $attemptSatu->fresh()->batas_akhir_at);
        $this->assertGreaterThan($batasDua + (6 * 60 * 1000), $attemptDua->fresh()->batas_akhir_at);
        Event::assertDispatched(PesertaWaktuDitambah::class, 2);
    }

    public function test_ragu_ragu_dan_highlight_tersimpan_tanpa_saling_menimpa(): void
    {
        [$organization, $admin, $participant, $session, $attempt] = $this->dasar('tanda_soal');
        $quiz = $session->quiz;
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal bertanda',
            'tipe' => 'pilihan_ganda',
            'bobot' => 1,
            'durasi_detik' => 60,
        ]);
        $opsi = Option::create([
            'question_id' => $question->id,
            'teks' => 'Opsi A',
            'is_correct' => true,
        ]);

        $attemptQuestion = AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'urutan' => 1,
            'urutan_opsi_json' => [$opsi->id],
        ]);

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.tandai', $attempt), [
                'question_id' => $question->id,
                'ragu_ragu' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ragu_ragu', true);

        $this->sebagaiPeserta($participant, $organization)
            ->postJson(route('peserta.attempt.tandai', $attempt), [
                'question_id' => $question->id,
                'highlight_soal' => true,
                'highlight_opsi' => [$opsi->id],
            ])
            ->assertOk()
            ->assertJsonPath('ragu_ragu', true)
            ->assertJsonPath('highlight_soal', true)
            ->assertJsonPath('highlight_opsi', [$opsi->id]);

        $attemptQuestion->refresh();
        $this->assertTrue($attemptQuestion->ragu_ragu);
        $this->assertTrue($attemptQuestion->highlight_soal);
        $this->assertSame([$opsi->id], $attemptQuestion->highlight_opsi_json);
    }

    public function test_peserta_selesai_muncul_di_leaderboard_proyektor(): void
    {
        [$organization, $admin, $participant, $session, $attempt] = $this->dasar('live_score');

        $this->sebagaiPeserta($participant, $organization)
            ->post(route('peserta.attempt.finish', $attempt))
            ->assertRedirect(route('peserta.attempt.result', $attempt));

        $state = app(QuizEngine::class)->displayState($session->fresh());

        $this->assertSame(1, $state['selesai']);
        $this->assertCount(1, $state['leaderboard']);
        $this->assertSame($participant->nama, $state['leaderboard'][0]['nama']);
    }

    public function test_hasil_akhir_menyertakan_attempt_lama_yang_sudah_mulai_tanpa_bergabung_at(): void
    {
        [$organization, , $participant, $session, $attempt] = $this->dasar('final_lama');

        $session->update(['status' => 'selesai']);

        $attempt->update([
            'status' => 'selesai',
            'bergabung_at' => null,
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
            'skor_total' => 80,
        ]);

        $absen = $this->peserta($organization, 'final_lama_absen');

        Attempt::create([
            'organization_id' => $organization->id,
            'user_id' => $absen->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
            'bergabung_at' => null,
            'mulai_at' => null,
            'skor_total' => 0,
        ]);

        $state = app(QuizEngine::class)->displayState($session->fresh());

        $this->assertSame('selesai', $state['status']);
        $this->assertSame(1, $state['total_peserta']);
        $this->assertCount(1, $state['leaderboard']);
        $this->assertSame($participant->nama, $state['leaderboard'][0]['nama']);
        $this->assertSame(1, $state['histogram'][8]);
    }

    public function test_hasil_akhir_kuis_percobaan_tetap_menghitung_histogram_tanpa_leaderboard(): void
    {
        [, , , $session, $attempt] = $this->dasar('final_percobaan');

        $session->quiz->update(['is_percobaan' => true]);
        $session->update(['status' => 'selesai']);
        $attempt->update([
            'status' => 'selesai',
            'selesai_at' => now()->getTimestampMs(),
            'skor_total' => 80,
        ]);

        $state = app(QuizEngine::class)->displayState($session->fresh());

        $this->assertTrue($state['percobaan']);
        $this->assertSame([], $state['leaderboard']);
        $this->assertSame(1, $state['histogram'][8]);
    }

    public function test_tutup_sesi_mengunci_attempt_yang_sudah_mulai_tanpa_bergabung_at(): void
    {
        Queue::fake();

        [, , $participant, $session, $attempt] = $this->dasar('tutup_lama');

        $attempt->update([
            'status' => 'berjalan',
            'bergabung_at' => null,
            'mulai_at' => now()->getTimestampMs(),
        ]);

        app(QuizEngine::class)->tutupSesi($session->fresh());

        $this->assertSame('selesai', $attempt->fresh()->status);
        $this->assertSame('selesai', $session->fresh()->status);

        $state = app(QuizEngine::class)->displayState($session->fresh());

        $this->assertCount(1, $state['leaderboard']);
        $this->assertSame($participant->nama, $state['leaderboard'][0]['nama']);
    }

    private function dasar(string $username, int $maxPelanggaran = 2): array
    {
        $organization = Organization::create([
            'name' => 'Kampus Kontrol',
            'slug' => 'kampus-kontrol-'.$username,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Kontrol',
            'username' => 'admin_'.$username,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-'.$username,
        ]);

        $participant = $this->peserta($organization, $username);

        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Kontrol',
            'pin' => 'K'.strtoupper(substr(md5($username), 0, 7)),
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Kontrol',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Kontrol',
            'mode' => 'acak',
            'status' => 'berjalan',
            'max_pelanggaran' => $maxPelanggaran,
            'durasi_peringatan' => 45,
            'leaderboard_live' => true,
            'display_token' => 'display-'.$username,
            'created_by' => $admin->id,
        ]);

        $attempt = $this->attempt($session, $participant);

        return [$organization, $admin, $participant, $session, $attempt];
    }

    private function peserta(Organization $organization, string $username): User
    {
        $user = User::create([
            'nama' => 'Peserta '.$username,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-'.$username,
        ]);

        $user->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        return $user;
    }

    private function attempt(QuizSession $session, User $participant): Attempt
    {
        return Attempt::create([
            'organization_id' => $session->organization_id,
            'user_id' => $participant->id,
            'quiz_session_id' => $session->id,
            'status' => 'berjalan',
            'bergabung_at' => now(),
            'batas_akhir_at' => now()->getTimestampMs() + (10 * 60 * 1000),
        ]);
    }

    private function sebagaiPeserta(User $participant, Organization $organization)
    {
        return $this->actingAs($participant)
            ->withSession([
                'auth_token' => $participant->current_session_token,
                'organization_id' => $organization->id,
            ]);
    }
}
