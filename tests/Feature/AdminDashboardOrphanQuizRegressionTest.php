<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Database\Seeders\TrialQuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardOrphanQuizRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_quiz_seeder_tidak_menimpa_organisasi_kuis_uji_coba_lama(): void
    {
        [$orgAlpha, $adminAlpha] = $this->createOrgWithUser('alpha', 'admin_alpha', 'admin');
        [$orgBeta] = $this->createOrgWithUser('beta', 'admin_beta', 'admin');

        $this->seed(TrialQuizSeeder::class);

        $quizAlpha = Quiz::withoutGlobalScopes()
            ->where('organization_id', $orgAlpha->id)
            ->where('judul', 'Kuis Uji Coba')
            ->first();

        $this->assertNotNull($quizAlpha);
        $this->assertSame(
            3,
            Question::withoutGlobalScopes()
                ->where('quiz_id', $quizAlpha->id)
                ->where('organization_id', $orgAlpha->id)
                ->count()
        );

        // Simulasikan perubahan keanggotaan admin pertama lalu deploy ulang menjalankan seeder.
        $adminAlpha->organizations()->detach($orgAlpha->id);
        $adminAlpha->organizations()->attach($orgBeta->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->seed(TrialQuizSeeder::class);

        $this->assertSame($orgAlpha->id, $quizAlpha->fresh()->organization_id);

        $quizBeta = Quiz::withoutGlobalScopes()
            ->where('organization_id', $orgBeta->id)
            ->where('judul', 'Kuis Uji Coba')
            ->first();

        $this->assertNotNull($quizBeta);
        $this->assertNotSame($quizAlpha->id, $quizBeta->id);
    }

    public function test_dashboard_admin_peserta_dan_display_tetap_200_saat_quiz_sesi_tidak_terbaca_scope(): void
    {
        [$orgAlpha, $adminAlpha] = $this->createOrgWithUser('alpha', 'admin_alpha', 'admin');
        $pesertaAlpha = $this->attachUserToOrg($orgAlpha, 'peserta_alpha', 'peserta');
        [$orgBeta, $adminBeta] = $this->createOrgWithUser('beta', 'admin_beta', 'admin');

        $quizLintasOrg = Quiz::withoutGlobalScopes()->create([
            'organization_id' => $orgBeta->id,
            'judul' => 'Kuis Terpisah Organisasi',
            'jumlah_soal' => 3,
            'acak_opsi' => true,
            'is_percobaan' => false,
            'created_by' => $adminBeta->id,
        ]);

        $sesiYatim = QuizSession::withoutGlobalScopes()->create([
            'organization_id' => $orgAlpha->id,
            'quiz_id' => $quizLintasOrg->id,
            'nama' => 'Sesi Uji Yatim',
            'mode' => 'acak',
            'acak_opsi' => true,
            'tampilkan_pembahasan' => false,
            'pembahasan_mode' => 'tersembunyi',
            'leaderboard_live' => true,
            'jeda_detik' => 5,
            'status' => 'berjalan',
            'display_token' => Str::random(48),
            'dibuka_at' => now(),
            'created_by' => $adminAlpha->id,
        ]);

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.sessions.index'))
            ->assertOk();

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->getJson(route('admin.dashboard.search', ['q' => 'Yatim']))
            ->assertOk();

        $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('peserta.dashboard'))
            ->assertOk();

        $this->actingAs($pesertaAlpha)
            ->withSession(['auth_token' => $pesertaAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->getJson(route('peserta.dashboard.state'))
            ->assertOk();

        $this->get(route('display.show', $sesiYatim->display_token))
            ->assertOk();

        $this->getJson(route('display.state', $sesiYatim->display_token))
            ->assertOk()
            ->assertJsonPath('nama', 'Sesi Uji Yatim');
    }

    public function test_migrasi_perbaikan_menyinkronkan_organisasi_quiz_dan_soal_yatim(): void
    {
        [$orgAlpha, $adminAlpha] = $this->createOrgWithUser('alpha', 'admin_alpha', 'admin');

        $quizTanpaOrg = Quiz::withoutGlobalScopes()->create([
            'organization_id' => null,
            'judul' => 'Kuis Uji Coba',
            'jumlah_soal' => 1,
            'acak_opsi' => true,
            'is_percobaan' => true,
            'created_by' => $adminAlpha->id,
        ]);

        $soalTanpaOrg = Question::withoutGlobalScopes()->create([
            'organization_id' => null,
            'quiz_id' => $quizTanpaOrg->id,
            'teks' => 'Soal Uji Yatim',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 60,
            'bobot' => 10,
        ]);

        QuizSession::withoutGlobalScopes()->create([
            'organization_id' => $orgAlpha->id,
            'quiz_id' => $quizTanpaOrg->id,
            'nama' => 'Uji Coba Sistem',
            'mode' => 'acak',
            'acak_opsi' => true,
            'tampilkan_pembahasan' => true,
            'pembahasan_mode' => 'terbuka',
            'leaderboard_live' => true,
            'jeda_detik' => 5,
            'status' => 'berjalan',
            'display_token' => Str::random(48),
            'dibuka_at' => now(),
            'created_by' => $adminAlpha->id,
        ]);

        $migrationPath = database_path('migrations/2026_11_29_000000_repair_orphaned_quiz_session_organizations.php');
        $this->assertFileExists($migrationPath);

        $migration = require $migrationPath;
        $migration->up();

        $this->assertSame($orgAlpha->id, Quiz::withoutGlobalScopes()->find($quizTanpaOrg->id)?->organization_id);
        $this->assertSame($orgAlpha->id, Question::withoutGlobalScopes()->find($soalTanpaOrg->id)?->organization_id);
    }

    private function createOrgWithUser(string $slug, string $username, string $role): array
    {
        $organization = Organization::create([
            'name' => 'Org '.ucfirst($slug),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $user = $this->attachUserToOrg($organization, $username, $role);

        return [$organization, $user];
    }

    private function attachUserToOrg(Organization $organization, string $username, string $role): User
    {
        $user = User::create([
            'nama' => ucfirst(str_replace('_', ' ', $username)),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);

        $organization->users()->attach($user->id, [
            'role' => $role === 'admin' ? 'owner' : 'participant',
            'status' => 'active',
        ]);

        return $user;
    }
}
