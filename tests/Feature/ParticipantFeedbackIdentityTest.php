<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\ParticipantFeedback;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParticipantFeedbackIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workspace_melihat_nama_kelas_dan_kuis_pada_masukan_peserta_organisasinya_saja(): void
    {
        [$orgAlpha, $adminAlpha, $pesertaAlpha, $kelasAlpha, $sessionAlpha] = $this->buatData('alpha', 'budi_ti22', 'Budi Santoso', 'TI-22A', 'UTS Jaringan');
        [$orgBeta, $adminBeta, $pesertaBeta, $kelasBeta, $sessionBeta] = $this->buatData('beta', 'siti_si22', 'Siti Rahma', 'SI-22B', 'UTS Basis Data');

        $attemptAlpha = Attempt::create([
            'organization_id' => $orgAlpha->id,
            'user_id' => $pesertaAlpha->id,
            'quiz_session_id' => $sessionAlpha->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'selesai_at' => now()->getTimestampMs(),
            'skor_total' => 90,
            'nilai' => 90,
        ]);

        $attemptBeta = Attempt::create([
            'organization_id' => $orgBeta->id,
            'user_id' => $pesertaBeta->id,
            'quiz_session_id' => $sessionBeta->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'selesai_at' => now()->getTimestampMs(),
            'skor_total' => 85,
            'nilai' => 85,
        ]);

        ParticipantFeedback::create([
            'attempt_id' => $attemptAlpha->id,
            'organization_id' => $orgAlpha->id,
            'user_id' => $pesertaAlpha->id,
            'status' => ParticipantFeedback::STATUS_SUBMITTED,
            'rating' => 5,
            'message' => 'Navigasi soal di lab sangat lancar.',
            'responded_at' => now(),
        ]);

        ParticipantFeedback::create([
            'attempt_id' => $attemptBeta->id,
            'organization_id' => $orgBeta->id,
            'user_id' => $pesertaBeta->id,
            'status' => ParticipantFeedback::STATUS_SUBMITTED,
            'rating' => 2,
            'message' => 'Komentar dari kampus lain.',
            'responded_at' => now(),
        ]);

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('budi_ti22')
            ->assertSee('TI-22A')
            ->assertSee('UTS Jaringan')
            ->assertSee('Navigasi soal di lab sangat lancar.')
            ->assertDontSee('Siti Rahma')
            ->assertDontSee('Komentar dari kampus lain.');

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.feedback.index', ['rating' => 3]))
            ->assertOk()
            ->assertDontSee('Budi Santoso');

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.feedback.index', ['kelas' => $kelasAlpha->id, 'rating' => 5]))
            ->assertOk()
            ->assertSee('Budi Santoso');
    }

    public function test_master_melihat_nama_peserta_kelas_dan_workspace_pada_masukan(): void
    {
        [$orgAlpha, , $pesertaAlpha, , $sessionAlpha] = $this->buatData('alpha', 'andi_if23', 'Andi Pratama', 'IF-23C', 'Kuis Algoritma');

        $attemptAlpha = Attempt::create([
            'organization_id' => $orgAlpha->id,
            'user_id' => $pesertaAlpha->id,
            'quiz_session_id' => $sessionAlpha->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'selesai_at' => now()->getTimestampMs(),
            'skor_total' => 95,
            'nilai' => 95,
        ]);

        ParticipantFeedback::create([
            'attempt_id' => $attemptAlpha->id,
            'organization_id' => $orgAlpha->id,
            'user_id' => $pesertaAlpha->id,
            'status' => ParticipantFeedback::STATUS_SUBMITTED,
            'rating' => 4,
            'message' => 'Tampilan jelas di layar laptop.',
            'responded_at' => now(),
        ]);

        $master = User::create([
            'nama' => 'Operator Platform',
            'username' => 'master_operator',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-operator-token',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.feedback.index'))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('andi_if23')
            ->assertSee('IF-23C')
            ->assertSee('Kuis Algoritma')
            ->assertSee($orgAlpha->name);
    }

    private function buatData(string $slug, string $usernamePeserta, string $namaPeserta, string $namaKelas, string $judulKuis): array
    {
        $org = Organization::create([
            'name' => 'Kampus '.ucfirst($slug),
            'slug' => 'kampus-'.$slug,
            'type' => 'education',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin '.ucfirst($slug),
            'username' => 'admin_'.$slug,
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-'.$slug,
        ]);

        $peserta = User::create([
            'nama' => $namaPeserta,
            'username' => $usernamePeserta,
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'token-'.$usernamePeserta,
        ]);

        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);
        $org->users()->attach($peserta->id, ['role' => 'participant', 'status' => 'active']);

        $kelas = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'nama' => $namaKelas,
            'pin' => strtoupper(substr($slug.'123456', 0, 6)),
            'created_by' => $admin->id,
        ]);
        $kelas->peserta()->attach($peserta->id);

        $quiz = Quiz::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => $judulKuis,
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi '.$judulKuis,
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'disp-'.$slug.'-12345',
            'created_by' => $admin->id,
        ]);

        return [$org, $admin, $peserta, $kelas, $session];
    }
}
