<?php

namespace Tests\Feature;

use App\Models\AiUsageEvent;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_hanya_melihat_data_organisasi_aktif(): void
    {
        [$organizationA, $adminA] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        [$organizationB, $adminB] = $this->organizationWithUser('beta', 'admin_beta', 'admin');

        $classA = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $organizationA->id,
            'nama' => 'Kelas Alpha',
            'pin' => 'ALPHA1',
            'created_by' => $adminA->id,
        ]);
        $classB = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $organizationB->id,
            'nama' => 'Kelas Beta',
            'pin' => 'BETA01',
            'created_by' => $adminB->id,
        ]);

        $this->actingAs($adminA)
            ->withSession(['auth_token' => $adminA->current_session_token, 'organization_id' => $organizationA->id])
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSee($classA->nama)
            ->assertDontSee($classB->nama);

        $this->actingAs($adminA)
            ->withSession(['auth_token' => $adminA->current_session_token, 'organization_id' => $organizationA->id])
            ->get('/admin/classes/'.$classB->id.'/edit')
            ->assertNotFound();
    }

    public function test_admin_tidak_bisa_mengubah_peserta_organisasi_lain(): void
    {
        [$organizationA, $adminA] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        [, $participantB] = $this->organizationWithUser('beta', 'participant_beta', 'peserta');

        $this->actingAs($adminA)
            ->withSession(['auth_token' => $adminA->current_session_token, 'organization_id' => $organizationA->id])
            ->get(route('admin.participants.edit', $participantB))
            ->assertNotFound();
    }

    public function test_master_dapat_membuat_organisasi_dan_owner(): void
    {
        [, $master] = $this->organizationWithUser('platform', 'master', 'admin');
        $master->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->post(route('master.organizations.store'), [
                'name' => 'Sekolah Harapan',
                'slug' => 'sekolah-harapan',
                'type' => 'education',
                'timezone' => 'Asia/Jakarta',
                'owner_name' => 'Owner Sekolah',
                'owner_username' => 'owner_harapan',
                'owner_email' => 'owner-harapan@example.test',
                'owner_password' => 'rahasia123',
            ])
            ->assertRedirect();

        $organization = Organization::where('slug', 'sekolah-harapan')->firstOrFail();
        $owner = User::where('username', 'owner_harapan')->firstOrFail();
        $this->assertSame('owner-harapan@example.test', $owner->email);
        $this->assertTrue($organization->users()->whereKey($owner->id)->wherePivot('role', 'owner')->exists());
        $this->assertTrue($owner->must_change_password);
    }

    public function test_pengaturan_admin_disimpan_pada_organisasi_aktif(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), [
                'organization_slug' => 'alpha-baru',
                'organization_timezone' => 'Asia/Jayapura',
                'site_name' => 'Kuis Alpha',
                'site_tagline' => 'Evaluasi untuk Alpha',
                'organization_name' => 'Alpha',
                'primary_color' => '#166534',
                'login_title' => 'Masuk Alpha',
                'login_description' => 'Gunakan akun organisasi Alpha.',
                'login_layout' => 'centered',
                'locale' => 'en',
                'allow_pin_enrollment' => 0,
                'ai_base_url' => null,
                'ai_api_key' => 'organization-secret-key',
                'ai_default_model' => 'provider/model',
                'ai_timeout' => 60,
                'class_cover_mode' => 'local',
                'image_base_url' => null,
                'image_api_key' => null,
                'image_model' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $organization->id,
            'site_name' => 'Kuis Alpha',
            'locale' => 'en',
            'allow_pin_enrollment' => false,
        ]);
        $organization->refresh();
        $this->assertSame('alpha-baru', $organization->slug);
        $this->assertSame('Asia/Jayapura', $organization->timezone);
        $this->assertSame('Kuis Alpha', OrganizationSetting::effective($organization->id)->site_name);
        $this->assertSame('organization-secret-key', OrganizationSetting::effective($organization->id)->ai_api_key);
        $this->assertNotSame(
            'organization-secret-key',
            DB::table('organization_settings')
                ->where('organization_id', $organization->id)
                ->value('ai_api_key'),
        );
    }

    public function test_anggota_dapat_berpindah_ke_organisasi_lain_yang_dimiliki(): void
    {
        [$organizationA, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        $organizationB = Organization::create([
            'name' => 'Beta',
            'slug' => 'beta',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        $organizationB->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organizationA->id])
            ->post(route('organizations.switch'), ['organization_id' => $organizationB->id])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('organization_id', $organizationB->id);
    }

    public function test_login_organisasi_memakai_branding_organisasi(): void
    {
        $organization = Organization::create([
            'name' => 'Alpha',
            'slug' => 'alpha-login',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'site_name' => 'Kuis Alpha',
            'site_tagline' => 'Belajar bersama Alpha',
            'primary_color' => '#166534',
            'login_title' => 'Masuk Alpha',
            'login_description' => 'Gunakan akun Alpha.',
            'login_layout' => 'centered',
            'ai_timeout' => 60,
            'class_cover_mode' => 'local',
        ]);

        $this->get(route('organizations.login', $organization))
            ->assertOk()
            ->assertSee('Kuis Alpha')
            ->assertSee('Masuk Alpha')
            ->assertSessionHas('organization_id', $organization->id);
    }

    public function test_login_organisasi_menolak_akun_dari_organisasi_lain(): void
    {
        [$organizationA] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        [, $participantB] = $this->organizationWithUser('beta', 'participant_beta', 'peserta');

        $this->withSession(['organization_id' => $organizationA->id])
            ->post(route('login.store'), [
                'username' => $participantB->username,
                'password' => 'password123',
            ])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_owner_dapat_menambahkan_admin_organisasi(): void
    {
        [$organization, $owner] = $this->organizationWithUser('alpha', 'owner_alpha', 'admin');

        $this->actingAs($owner)
            ->withSession(['auth_token' => $owner->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.team.store'), [
                'nama' => 'Admin Kedua',
                'username' => 'admin_kedua',
                'email' => 'admin-kedua@example.test',
                'password' => 'password123',
            ])
            ->assertRedirect();

        $admin = User::where('username', 'admin_kedua')->firstOrFail();
        $this->assertSame('admin-kedua@example.test', $admin->email);
        $this->assertTrue($organization->users()->whereKey($admin->id)->wherePivot('role', 'admin')->exists());
        $this->assertTrue($admin->must_change_password);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'admin_added',
            'subject_type' => 'user',
            'subject_id' => $admin->id,
            'actor_id' => $owner->id,
        ]);
    }

    public function test_admin_baru_di_organisasi_tunggal_tidak_menjadi_owner(): void
    {
        $organization = Organization::query()->firstOrFail();
        $owner = User::create([
            'nama' => 'Owner Utama',
            'username' => 'owner_utama',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'owner-token',
        ]);
        $organization->users()->updateExistingPivot($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->withSession(['auth_token' => $owner->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.team.store'), [
                'nama' => 'Admin Terbatas',
                'username' => 'admin_terbatas',
                'email' => 'admin-terbatas@example.test',
                'password' => 'password123',
            ])
            ->assertRedirect();

        $admin = User::where('username', 'admin_terbatas')->firstOrFail();
        $this->assertTrue($organization->users()->whereKey($admin->id)->wherePivot('role', 'admin')->exists());
        $this->assertFalse($organization->users()->whereKey($admin->id)->wherePivot('role', 'owner')->exists());
    }

    public function test_admin_biasa_tidak_dapat_mengelola_tim(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        $organization->users()->updateExistingPivot($admin->id, ['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.team.index'))
            ->assertForbidden();
    }

    public function test_slug_organisasi_tidak_boleh_bertabrakan(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        Organization::create([
            'name' => 'Beta',
            'slug' => 'beta',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->settingsPayload($organization, [
                'organization_slug' => 'beta',
            ]))
            ->assertSessionHasErrors('organization_slug');

        $this->assertSame('alpha', $organization->fresh()->slug);
    }

    public function test_kebijakan_pin_organisasi_menolak_pendaftaran_peserta(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        $peserta = User::create([
            'nama' => 'Peserta Alpha',
            'username' => 'peserta_alpha',
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-alpha-token',
        ]);
        $organization->users()->attach($peserta->id, ['role' => 'participant', 'status' => 'active']);
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'allow_pin_enrollment' => false,
        ]);
        $kelas = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Tertutup',
            'pin' => 'AB12',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $organization->id])
            ->post(route('peserta.kelas.masuk', $kelas), ['pin' => 'AB12'])
            ->assertSessionHasErrors('pin');

        $this->assertFalse($kelas->peserta()->whereKey($peserta->id)->exists());
    }

    public function test_zona_waktu_organisasi_dipakai_di_riwayat_akses(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        $organization->update(['timezone' => 'Asia/Jayapura']);

        $log = AuditLog::create([
            'organization_id' => $organization->id,
            'actor_id' => $admin->id,
            'action' => 'admin_added',
            'subject_type' => 'user',
            'subject_id' => $admin->id,
            'metadata' => ['name' => 'Admin Alpha'],
            'ip_address' => '127.0.0.1',
        ]);
        $log->forceFill(['created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC')])->save();

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('01 Jan 2026, 09:00');
    }

    public function test_admin_melihat_pratinjau_branding_di_halaman_pengaturan(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'site_name' => 'Kuis Alpha',
            'site_tagline' => 'Belajar bersama',
            'primary_color' => '#166534',
            'login_title' => 'Masuk Alpha',
            'login_description' => 'Gunakan akun Alpha.',
            'login_layout' => 'split',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Pratinjau branding')
            ->assertSee('x-model="siteName"', false)
            ->assertSee('x-model="loginTitle"', false)
            ->assertSee('x-text="siteName"', false)
            ->assertSee('x-text="loginTitle"', false)
            ->assertSee('logoPreview', false);
    }

    public function test_upload_logo_ditolak_saat_kuota_penyimpanan_habis(): void
    {
        config(['platform.storage_mb' => 0]);
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->settingsPayload($organization, [
                'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
            ]))
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseMissing('organization_settings', [
            'organization_id' => $organization->id,
        ]);
    }

    public function test_halaman_pengaturan_menampilkan_pemakaian_ai_dan_penyimpanan(): void
    {
        config(['platform.ai_monthly_requests' => 10, 'platform.storage_mb' => 1]);
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        AiUsageEvent::create([
            'organization_id' => $organization->id,
            'kind' => 'questions',
            'requests' => 3,
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', str_repeat('a', 1024));
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'logo_path' => 'branding/logo.png',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Pemakaian dan kuota')
            ->assertSee('3 / 10 request');
    }

    public function test_bahasa_organisasi_mengubah_nama_bulan(): void
    {
        [$organization, $admin] = $this->organizationWithUser('alpha', 'admin_alpha', 'admin');
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'locale' => 'en',
        ]);
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00', 'UTC'));

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('15 January 2026')
            ->assertDontSee('15 Januari 2026');
    }

    private function settingsPayload(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_slug' => $organization->slug,
            'organization_timezone' => $organization->timezone,
            'site_name' => $organization->name,
            'site_tagline' => 'Kuis organisasi',
            'organization_name' => $organization->name,
            'primary_color' => '#166534',
            'login_title' => 'Masuk',
            'login_description' => 'Gunakan akun organisasi.',
            'login_layout' => 'centered',
            'locale' => 'id',
            'allow_pin_enrollment' => 1,
            'ai_base_url' => null,
            'ai_api_key' => null,
            'ai_default_model' => null,
            'ai_timeout' => 60,
            'class_cover_mode' => 'local',
            'image_base_url' => null,
            'image_api_key' => null,
            'image_model' => null,
        ], $overrides);
    }

    private function organizationWithUser(string $slug, string $username, string $role): array
    {
        $organization = Organization::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        $user = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);
        $organization->users()->attach($user->id, [
            'role' => $role === 'admin' ? 'owner' : 'participant',
            'status' => 'active',
        ]);

        return [$organization, $user];
    }
}
