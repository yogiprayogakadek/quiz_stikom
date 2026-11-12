<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationNameSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dapat_mengubah_nama_organisasi(): void
    {
        $owner = $this->user('owner_nama', 'admin');
        $organization = $owner->organizations()->firstOrFail();
        $owner->organizations()->updateExistingPivot($organization->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner_nama-token', 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->payload($organization, ['organization_name' => 'Nama Baru']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $organization->fresh()->name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization_renamed',
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['auth_token' => 'owner_nama-token', 'organization_id' => $organization->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('name="organization_name"', false)
            ->assertSee('Nama Baru');
    }

    public function test_admin_biasa_tidak_dapat_mengubah_nama_organisasi(): void
    {
        $admin = $this->user('admin_nama', 'admin');
        $organization = $admin->organizations()->firstOrFail();
        $original = $organization->name;

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin_nama-token', 'organization_id' => $organization->id])
            ->put(route('admin.settings.update'), $this->payload($organization, ['organization_name' => 'Nama Lain']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($original, $organization->fresh()->name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'organization_renamed']);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin_nama-token', 'organization_id' => $organization->id])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertDontSee('name="organization_name"', false);
    }

    private function user(string $username, string $role): User
    {
        return User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);
    }

    private function payload(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_slug' => $organization->slug,
            'organization_timezone' => $organization->timezone,
            'site_name' => 'Kuis Publik',
            'site_tagline' => 'Belajar dan evaluasi bersama',
            'primary_color' => '#166534',
            'login_title' => 'Masuk ke kelas',
            'login_description' => 'Gunakan akun yang telah diberikan.',
            'login_layout' => 'centered',
            'locale' => 'id',
            'allow_pin_enrollment' => 1,
            'ai_timeout' => 90,
            'class_cover_mode' => 'local',
        ], $overrides);
    }
}