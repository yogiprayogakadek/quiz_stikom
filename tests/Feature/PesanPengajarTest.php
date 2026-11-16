<?php

namespace Tests\Feature;

use App\Events\PesanPengajar;
use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PesanPengajarTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_pesan_disiarakan_ke_kanal_peserta(): void
    {
        $event = new PesanPengajar(9, 42, ['id' => 1, 'teks' => 'Halo', 'tipe' => 'info', 'waktu' => '10:15']);

        $this->assertSame('session-user.9.42', $event->broadcastOn()[0]->name);
        $this->assertSame('PesanPengajar', $event->broadcastAs());
        $this->assertSame('Halo', $event->broadcastWith()['teks']);
    }

    public function test_pesan_aktif_hanya_yang_belum_ditutup(): void
    {
        [, , $attempts] = $this->sesiBerjalan(1);
        $attempt = $attempts[0];
        $aktif = SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Sisa waktu 10 menit.',
            'tipe' => 'info',
        ]);
        SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Sudah ditutup.',
            'tipe' => 'info',
            'ditutup_at' => now(),
        ]);

        $hasil = SessionMessage::aktifUntukAttempt($attempt->id);

        $this->assertCount(1, $hasil);
        $this->assertSame($aktif->id, $hasil->first()->id);
        $this->assertSame(['id', 'teks', 'tipe', 'waktu'], array_keys($hasil->first()->untukPeserta()));
    }

    public function test_blast_mengirim_ke_semua_peserta_yang_sedang_mengerjakan(): void
    {
        Event::fake([PesanPengajar::class]);
        [$admin, $session, , $organization] = $this->sesiBerjalan(2);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.pesan', $session), ['teks' => 'Sisa waktu 10 menit.', 'target' => 'semua'])
            ->assertRedirect();

        $this->assertSame(2, SessionMessage::where('quiz_session_id', $session->id)->count());
        Event::assertDispatchedTimes(PesanPengajar::class, 2);
    }

    public function test_pilih_peserta_hanya_mengirim_ke_attempt_terpilih(): void
    {
        Event::fake([PesanPengajar::class]);
        [$admin, $session, $attempts, $organization] = $this->sesiBerjalan(2);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.pesan', $session), [
                'teks' => 'Periksa soal nomor 3.',
                'target' => 'terpilih',
                'attempt_ids' => [$attempts[0]->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, SessionMessage::count());
        $this->assertSame($attempts[0]->id, SessionMessage::first()->attempt_id);
    }

    public function test_pesan_kosong_dan_sesi_selesai_ditolak(): void
    {
        [$admin, $session, , $organization] = $this->sesiBerjalan(1);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.pesan', $session), ['teks' => '  ', 'target' => 'semua'])
            ->assertSessionHasErrors('teks');

        $session->update(['status' => 'selesai']);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.sessions.pesan', $session), ['teks' => 'Halo', 'target' => 'semua'])
            ->assertSessionHasErrors('teks');

        $this->assertSame(0, SessionMessage::count());
    }

    public function test_peserta_menutup_pesannya_sendiri_dan_idempoten(): void
    {
        [, , $attempts] = $this->sesiBerjalan(1);
        $attempt = $attempts[0];
        $pesan = SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Halo',
            'tipe' => 'info',
        ]);

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->postJson(route('peserta.pesan.tutup', $pesan))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($pesan->fresh()->ditutup_at);

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->postJson(route('peserta.pesan.tutup', $pesan))
            ->assertOk();
    }

    public function test_peserta_lain_tidak_bisa_menutup_pesan_orang(): void
    {
        [, , $attempts] = $this->sesiBerjalan(2);
        $attempt = $attempts[0];
        $pesan = SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Halo',
            'tipe' => 'info',
        ]);
        $lain = User::whereKeyNot($attempt->user_id)->where('role', 'peserta')->firstOrFail();

        $this->actingAs($lain)
            ->withSession(['auth_token' => $lain->current_session_token])
            ->postJson(route('peserta.pesan.tutup', $pesan))
            ->assertForbidden();
    }

    public function test_panel_admin_menampilkan_composer_pesan_saat_sesi_berjalan(): void
    {
        [$admin, $session, , $organization] = $this->sesiBerjalan(1);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.show', $session))
            ->assertOk()
            ->assertSee('Kirim pesan')
            ->assertSee('name="teks"', false);

        $session->update(['status' => 'selesai']);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.sessions.show', $session))
            ->assertOk()
            ->assertDontSee('Kirim pesan');
    }

    public function test_halaman_ujian_dan_polling_membawa_pesan_aktif(): void
    {
        [, , $attempts] = $this->sesiBerjalan(1);
        $attempt = $attempts[0];
        $pesan = SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Tetap fokus, sisa 10 menit.',
            'tipe' => 'info',
        ]);

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->get(route('peserta.attempt.show', $attempt))
            ->assertOk()
            ->assertSee('Tetap fokus, sisa 10 menit.');

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->getJson(route('peserta.attempt.status', $attempt))
            ->assertOk()
            ->assertJsonPath('pesan.0.id', $pesan->id)
            ->assertJsonPath('pesan.0.teks', 'Tetap fokus, sisa 10 menit.');

        $pesan->update(['ditutup_at' => now()]);

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->getJson(route('peserta.attempt.status', $attempt))
            ->assertOk()
            ->assertJsonPath('pesan', []);
    }

    /**
     * Organisasi, admin, sesi acak berjalan, dan attempt pesertanya.
     *
     * @return array{0: User, 1: QuizSession, 2: \Illuminate\Support\Collection<int, Attempt>, 3: Organization}
     */
    private function sesiBerjalan(int $jumlahPeserta = 2): array
    {
        $slug = 'pesan-'.Str::lower(Str::random(6));

        $organization = Organization::create([
            'name' => 'Kampus Pesan',
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Pesan',
            'username' => 'admin_'.$slug,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-'.$slug,
        ]);

        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Pesan',
            'pin' => 'P'.strtoupper(substr(md5($slug), 0, 7)),
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Pesan',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Pesan',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'display-'.$slug,
            'created_by' => $admin->id,
        ]);

        $attempts = collect();

        for ($i = 0; $i < $jumlahPeserta; $i++) {
            $peserta = User::create([
                'nama' => 'Peserta '.$i,
                'username' => 'peserta_'.$slug.'_'.$i,
                'password' => Hash::make('password'),
                'role' => 'peserta',
                'current_session_token' => 'token-'.$slug.'-'.$i,
            ]);

            $peserta->organizations()->syncWithoutDetaching([
                $organization->id => ['role' => 'participant', 'status' => 'active'],
            ]);

            $attempts->push(Attempt::create([
                'organization_id' => $organization->id,
                'user_id' => $peserta->id,
                'quiz_session_id' => $session->id,
                'status' => 'berjalan',
                'bergabung_at' => now(),
                'batas_akhir_at' => now()->getTimestampMs() + (10 * 60 * 1000),
            ]));
        }

        return [$admin, $session, $attempts->sortBy('id')->values(), $organization];
    }
}
