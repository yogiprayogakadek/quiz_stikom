<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KelasTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_membuat_kelas_dan_mengatur_anggota_dan_kuis(): void
    {
        $admin = $this->admin();
        $peserta = $this->peserta('anggota');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post('/admin/classes', ['nama' => 'Kelas A', 'pin' => '1234'])
            ->assertRedirect();

        $kelas = Kelas::where('pin', '1234')->firstOrFail();
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Kelas', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->put("/admin/classes/{$kelas->id}", [
                'nama' => 'Kelas A',
                'pin' => '1234',
                'peserta_ids' => [$peserta->id],
                'quiz_ids' => [$quiz->id],
            ])
            ->assertRedirect();

        $this->assertTrue($kelas->peserta()->whereKey($peserta->id)->exists());
        $this->assertTrue($kelas->quizzes()->whereKey($quiz->id)->exists());

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get("/admin/classes/{$kelas->id}/edit")
            ->assertOk()
            ->assertSee('Kelas A')
            ->assertSee($peserta->nama);
    }

    public function test_peserta_memilih_kelas_lalu_masuk_dengan_pin(): void
    {
        $admin = $this->admin();
        $peserta = $this->peserta('pin');
        $kelas = Kelas::create(['nama' => 'Kelas PIN', 'pin' => 'AB12', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Kelas PIN', 'created_by' => $admin->id]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get('/kelas')
            ->assertOk()
            ->assertSee('Kelas PIN')
            ->assertSee('Perlu PIN');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get("/kelas/{$kelas->id}")
            ->assertOk()
            ->assertSee('PIN kelas')
            ->assertDontSee('Kuis di kelas');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->post("/kelas/{$kelas->id}/masuk", ['pin' => 'salah'])
            ->assertSessionHasErrors('pin');

        $this->assertFalse($kelas->peserta()->whereKey($peserta->id)->exists());

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->post("/kelas/{$kelas->id}/masuk", ['pin' => 'AB12'])
            ->assertRedirect(route('peserta.kelas.show', $kelas));

        $this->assertTrue($kelas->peserta()->whereKey($peserta->id)->exists());

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get("/kelas/{$kelas->id}")
            ->assertOk()
            ->assertSee('Kuis di kelas')
            ->assertSee('Kuis Kelas PIN')
            ->assertSee('Sesi yang dibuka');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kelas saya')
            ->assertSee(route('peserta.kelas.show', $kelas));
    }

    public function test_sesi_kelas_hanya_tampil_untuk_anggota_kelas(): void
    {
        $admin = $this->admin();
        $anggota = $this->peserta('anggota');
        $luar = $this->peserta('luar');
        $sesi = $this->sesiKelas($admin, $anggota);

        $this->actingAs($anggota)
            ->withSession(['auth_token' => $anggota->current_session_token])
            ->getJson('/dashboard/state')
            ->assertOk()
            ->assertJsonPath('sesi_tersedia.0.id', $sesi->id);

        $this->actingAs($anggota)
            ->withSession(['auth_token' => $anggota->current_session_token])
            ->get("/kelas/{$sesi->kelas_id}")
            ->assertOk()
            ->assertSee('Sesi Kelas')
            ->assertSee('Gabung sesi');

        $this->actingAs($luar)
            ->withSession(['auth_token' => $luar->current_session_token])
            ->getJson('/dashboard/state')
            ->assertOk()
            ->assertJsonCount(0, 'sesi_tersedia');

        $this->actingAs($luar)
            ->withSession(['auth_token' => $luar->current_session_token])
            ->get("/kelas/{$sesi->kelas_id}")
            ->assertOk()
            ->assertSee('PIN kelas')
            ->assertDontSee('Sesi Kelas');
    }

    public function test_peserta_di_luar_kelas_tidak_bisa_gabung_sesi_kelas(): void
    {
        $admin = $this->admin();
        $anggota = $this->peserta('anggota');
        $luar = $this->peserta('luar');
        $sesi = $this->sesiKelas($admin, $anggota);

        $this->actingAs($luar)
            ->withSession(['auth_token' => $luar->current_session_token])
            ->post("/sesi/{$sesi->id}/gabung")
            ->assertSessionHasErrors('gabung');

        $this->assertDatabaseMissing('attempts', [
            'quiz_session_id' => $sesi->id,
            'user_id' => $luar->id,
        ]);
    }

    public function test_sesi_baru_untuk_kelas_otomatis_berisi_anggota_kelas(): void
    {
        $admin = $this->admin();
        $anggota = $this->peserta('anggota');
        $luar = $this->peserta('luar');
        $kelas = Kelas::create(['nama' => 'Kelas Sesi', 'pin' => 'KELAS1', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Kelas', 'created_by' => $admin->id]);
        $kelas->peserta()->attach($anggota->id);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post('/admin/sessions', [
                'quiz_id' => $quiz->id,
                'kelas_id' => $kelas->id,
                'nama' => 'Sesi Kelas Baru',
                'mode' => 'acak',
                'jeda_detik' => 5,
            ])
            ->assertRedirect();

        $sesi = QuizSession::where('nama', 'Sesi Kelas Baru')->firstOrFail();

        $this->assertSame(
            [$anggota->id],
            $sesi->allowedParticipants()->pluck('users.id')->all()
        );
        $this->assertFalse($sesi->isUserAllowed($luar));
    }

    private function sesiKelas(User $admin, User $anggota): QuizSession
    {
        $kelas = Kelas::create(['nama' => 'Kelas Sesi', 'pin' => 'KELAS1', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Kelas', 'created_by' => $admin->id]);
        $kelas->peserta()->attach($anggota->id);

        return QuizSession::create([
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Kelas',
            'mode' => 'acak',
            'status' => 'lobby',
            'display_token' => 'tokenkelas',
            'dibuka_at' => now(),
            'created_by' => $admin->id,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'nama' => 'Admin Kelas',
            'username' => 'admin-kelas',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-kelas',
        ]);
    }

    private function peserta(string $slug): User
    {
        return User::create([
            'nama' => 'Peserta '.$slug,
            'username' => 'peserta-'.$slug,
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-'.$slug,
        ]);
    }
}
