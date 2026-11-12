<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_membuat_workspace_dengan_owner_existing(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_lama');
        $existingOrganization = $this->organization('lama', 'Lama');
        $existingOrganization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);
        $userCount = User::count();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->post(route('master.organizations.store'), [
                'name' => 'Workspace Kedua',
                'slug' => 'workspace-kedua',
                'type' => 'education',
                'timezone' => 'Asia/Jakarta',
                'owner_user_id' => $owner->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $organization = Organization::where('slug', 'workspace-kedua')->firstOrFail();

        $this->assertTrue($organization->owners()->whereKey($owner->id)->exists());
        $this->assertSame($userCount, User::count());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'organization_created',
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
    }

    public function test_master_melihat_daftar_owner_dan_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_alpha');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.owners.index'))
            ->assertOk()
            ->assertSee('owner_alpha')
            ->assertSee('Alpha');
    }

    public function test_admin_biasa_tidak_dapat_melihat_daftar_owner(): void
    {
        $owner = $this->admin('owner_biasa');

        $this->actingAs($owner)
            ->withSession(['auth_token' => $owner->current_session_token])
            ->get(route('master.owners.index'))
            ->assertForbidden();
    }

    public function test_master_memindahkan_owner_dan_owner_lama_tetap_admin(): void
    {
        $master = $this->master();
        $oldOwner = $this->admin('owner_lama');
        $newOwner = $this->admin('admin_baru');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($oldOwner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.organizations.owner.update', $organization), [
                'new_owner_id' => $newOwner->id,
                'previous_owner_action' => 'keep_admin',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.organizations.index'));

        $this->assertTrue($organization->owners()->whereKey($newOwner->id)->exists());
        $this->assertSame(1, $organization->owners()->count());
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $oldOwner->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'ownership_transferred',
        ]);
    }

    public function test_master_memindahkan_owner_dan_mengeluarkan_owner_lama(): void
    {
        $master = $this->master();
        $oldOwner = $this->admin('owner_lama');
        $newOwner = $this->admin('admin_baru');
        $organization = $this->organization('beta', 'Beta');
        $organization->users()->attach($oldOwner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.organizations.owner.update', $organization), [
                'new_owner_id' => $newOwner->id,
                'previous_owner_action' => 'remove',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.organizations.index'));

        $this->assertTrue($organization->owners()->whereKey($newOwner->id)->exists());
        $this->assertSame(1, $organization->owners()->count());
        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $oldOwner->id,
        ]);
    }

    public function test_master_menolak_transfer_ke_owner_yang_sama(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_lama');
        $organization = $this->organization('gamma', 'Gamma');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.organizations.owner.update', $organization), [
                'new_owner_id' => $owner->id,
                'previous_owner_action' => 'keep_admin',
            ])
            ->assertSessionHasErrors('new_owner_id');

        $this->assertSame(1, $organization->owners()->count());
    }

    public function test_admin_biasa_tidak_dapat_memindahkan_owner(): void
    {
        $admin = $this->admin('admin_biasa');
        $newOwner = $this->admin('admin_baru');
        $organization = $this->organization('delta', 'Delta');
        $organization->users()->attach($admin->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->put(route('master.organizations.owner.update', $organization), [
                'new_owner_id' => $newOwner->id,
                'previous_owner_action' => 'keep_admin',
            ])
            ->assertForbidden();
    }

    private function master(): User
    {
        $master = User::create([
            'nama' => 'Master',
            'username' => 'master',
            'email' => 'master@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-token',
        ]);
        $master->forceFill(['email_verified_at' => now()])->save();

        return $master;
    }

    private function admin(string $username): User
    {
        $admin = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => $username.'-token',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    private function organization(string $slug, string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }
}
