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
