<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterOrganizationRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_dapat_mengubah_nama_workspace_mana_saja(): void
    {
        $master = $this->master();
        $organization = $this->organization('workspace-master');
        $original = $organization->name;

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.organizations.name.update', $organization), ['name' => 'Nama Dari Master'])
            ->assertRedirect(route('master.organizations.index'))
            ->assertSessionHas('sukses');

        $this->assertSame('Nama Dari Master', $organization->fresh()->name);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.organizations.name.edit', $organization))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('Nama Dari Master');

        $audit = AuditLog::query()
            ->where('action', 'organization_renamed')
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('master', $audit->metadata['source']);
        $this->assertSame($original, $audit->metadata['from']);
        $this->assertSame('Nama Dari Master', $audit->metadata['to']);
    }

    public function test_admin_biasa_ditolak_dari_halaman_rename_master(): void
    {
        $admin = $this->user('admin_rename', 'admin');
        $organization = $this->organization('workspace-admin-rename');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('master.organizations.name.edit', $organization))
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->put(route('master.organizations.name.update', $organization), ['name' => 'Tidak Boleh'])
            ->assertForbidden();

        $this->assertSame('Workspace workspace-admin-rename', $organization->fresh()->name);
    }

    private function master(): User
    {
        return $this->user('master_rename', 'admin', [
            'is_platform_admin' => true,
            'email' => 'master-rename@example.test',
        ]);
    }

    private function user(string $username, string $role, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'nama' => ucfirst($username),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ], $overrides));

        if (isset($overrides['email'])) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function organization(string $slug): Organization
    {
        return Organization::create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }
}
