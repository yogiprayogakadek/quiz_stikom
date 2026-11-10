<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_dapat_export_data_organisasi(): void
    {
        Storage::fake('public');

        [$organization, $owner, $participant] = $this->seedTenant();
        $master = $this->master();

        $response = $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->get(route('master.organizations.export', $organization));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $data = $response->json();

        $this->assertSame($organization->id, $data['organization']['id']);
        $this->assertCount(1, $data['classes']);
        $this->assertCount(1, $data['quizzes']);
        $this->assertCount(1, $data['questions']);
        $this->assertCount(1, $data['options']);
        $this->assertCount(1, $data['sessions']);
        $this->assertCount(1, $data['attempts']);
        $this->assertCount(1, $data['answers']);
        $this->assertCount(1, $data['events']);
        $this->assertCount(1, $data['ai_usage']);
        $this->assertCount(1, $data['participant_login_activities']);
        $this->assertCount(1, $data['audit_logs']);
        $this->assertCount(2, $data['users']);
        $this->assertArrayNotHasKey('password', $data['users'][0]);
        $this->assertArrayNotHasKey('current_session_token', $data['users'][0]);
        $this->assertArrayNotHasKey('ai_api_key', $data['settings']);
        $this->assertArrayNotHasKey('image_api_key', $data['settings']);
        $this->assertSame($owner->id, $data['memberships'][0]['user_id']);
        $this->assertSame($participant->id, $data['memberships'][1]['user_id']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization_exported',
            'organization_id' => $organization->id,
        ]);
    }

    public function test_admin_organisasi_tidak_dapat_export(): void
    {
        [$organization] = $this->seedTenant();
        $admin = $this->admin($organization);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->get(route('master.organizations.export', $organization))
            ->assertForbidden();
    }

    public function test_hapus_organisasi_menolak_konfirmasi_salah(): void
    {
        [$organization] = $this->seedTenant();
        $master = $this->master();

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->delete(route('master.organizations.destroy', $organization), ['konfirmasi' => 'slug-salah'])
            ->assertSessionHasErrors('konfirmasi');

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_hapus_organisasi_menghapus_data_tenant_dan_file(): void
    {
        Storage::fake('public');

        [$organization, $owner, $participant, $files] = $this->seedTenant();
        $master = $this->master();

        $this->actingAs($master)
            ->withSession(['auth_token' => 'master-token'])
            ->delete(route('master.organizations.destroy', $organization), ['konfirmasi' => $organization->slug])
            ->assertRedirect(route('master.organizations.index'));

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('organization_memberships', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('organization_settings', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('kelas', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('quizzes', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('questions', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('quiz_sessions', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('attempts', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('ai_generations', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('ai_usage_events', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('participant_login_activities', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('audit_logs', ['organization_id' => $organization->id]);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('users', ['id' => $participant->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization_deleted',
            'organization_id' => null,
            'subject_id' => $organization->id,
        ]);

        Storage::disk('public')->assertMissing($files);
    }

    public function test_admin_organisasi_tidak_dapat_menghapus(): void
    {
        [$organization] = $this->seedTenant();
        $admin = $this->admin($organization);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->delete(route('master.organizations.destroy', $organization), ['konfirmasi' => $organization->slug])
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    private function seedTenant(): array
    {
        $organization = Organization::create([
            'name' => 'Organisasi Data',
            'slug' => 'organisasi-data',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $owner = User::create([
            'nama' => 'Owner Data',
            'username' => 'owner-data',
            'email' => 'owner-data@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $participant = User::create([
            'nama' => 'Peserta Data',
            'username' => 'peserta-data',
            'email' => 'peserta-data@example.test',
            'password' => Hash::make('password123'),
            'role' => 'peserta',
        ]);
        $organization->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
            $participant->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        Storage::disk('public')->put('branding/logo-data.png', 'logo');
        Storage::disk('public')->put('class-covers/cover-data.png', 'cover');

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'logo_path' => 'branding/logo-data.png',
            'ai_api_key' => 'secret-ai',
            'image_api_key' => 'secret-image',
        ]);

        $classId = DB::table('kelas')->insertGetId([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Data',
            'pin' => 'DATA1',
            'cover_path' => 'class-covers/cover-data.png',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'organization_id' => $organization->id,
            'kelas_id' => $classId,
            'judul' => 'Kuis Data',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = DB::table('questions')->insertGetId([
            'organization_id' => $organization->id,
            'teks' => 'Apa itu HTTP?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 100,
            'durasi_detik' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('options')->insert([
            'question_id' => $questionId,
            'teks' => 'Hypertext Transfer Protocol',
            'is_correct' => true,
        ]);
        DB::table('quiz_questions')->insert(['quiz_id' => $quizId, 'question_id' => $questionId]);
        DB::table('kelas_quiz')->insert([
            'kelas_id' => $classId,
            'quiz_id' => $quizId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kelas_user')->insert([
            'kelas_id' => $classId,
            'user_id' => $participant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = DB::table('quiz_sessions')->insertGetId([
            'organization_id' => $organization->id,
            'quiz_id' => $quizId,
            'kelas_id' => $classId,
            'nama' => 'Sesi Data',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => Str::random(64),
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('session_questions')->insert([
            'quiz_session_id' => $sessionId,
            'question_id' => $questionId,
            'urutan' => 1,
        ]);

        $attemptId = DB::table('attempts')->insertGetId([
            'organization_id' => $organization->id,
            'user_id' => $participant->id,
            'quiz_session_id' => $sessionId,
            'status' => 'selesai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attempt_questions')->insert([
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'urutan' => 1,
            'urutan_opsi_json' => json_encode([]),
        ]);
        DB::table('answers')->insert([
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'answered_at' => now(),
        ]);
        DB::table('attempt_events')->insert([
            'attempt_id' => $attemptId,
            'tipe' => 'mulai',
            'created_at' => now(),
        ]);
        DB::table('ai_generations')->insert([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'quiz_id' => $quizId,
            'status' => 'selesai',
            'params' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_usage_events')->insert([
            'organization_id' => $organization->id,
            'kind' => 'questions',
            'requests' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('participant_login_activities')->insert([
            'organization_id' => $organization->id,
            'user_id' => $participant->id,
            'nama' => $participant->nama,
            'username' => $participant->username,
            'status' => 'berhasil',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'organization_id' => $organization->id,
            'actor_id' => $owner->id,
            'action' => 'quiz_created',
            'subject_type' => 'quiz',
            'subject_id' => $quizId,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $organization,
            $owner,
            $participant,
            ['branding/logo-data.png', 'class-covers/cover-data.png'],
        ];
    }

    private function master(): User
    {
        return User::create([
            'nama' => 'Master Platform',
            'username' => 'master-data',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-token',
        ]);
    }

    private function admin(Organization $organization): User
    {
        $admin = User::create([
            'nama' => 'Admin Organisasi',
            'username' => 'admin-data',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'admin-token',
        ]);

        $organization->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        return $admin;
    }
}
