<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceRequest;
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

    public function test_master_menghapus_owner_tanpa_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_yatim');
        $owner->organizations()->detach();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->delete(route('master.owners.destroy', $owner))
            ->assertRedirect(route('master.owners.index'))
            ->assertSessionHas('sukses');

        $this->assertSoftDeleted('users', ['id' => $owner->id]);
        $this->assertNull(User::withTrashed()->findOrFail($owner->id)->current_session_token);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'owner_deleted',
            'subject_type' => 'user',
            'subject_id' => $owner->id,
            'actor_id' => $master->id,
        ]);
    }

    public function test_master_tidak_dapat_menghapus_owner_yang_masih_memiliki_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_aktif');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->delete(route('master.owners.destroy', $owner))
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
    }

    public function test_master_tidak_dapat_menghapus_admin_yang_masih_anggota_workspace(): void
    {
        $master = $this->master();
        $admin = $this->admin('admin_anggota');
        $organization = $this->organization('beta', 'Beta');
        $organization->users()->attach($admin->id, [
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->delete(route('master.owners.destroy', $admin))
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_master_tidak_dapat_menghapus_owner_dengan_permintaan_workspace_pending(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_pending');
        $owner->organizations()->detach();
        WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Pending',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->delete(route('master.owners.destroy', $owner))
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
    }

    public function test_master_tidak_dapat_menghapus_platform_admin(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->delete(route('master.owners.destroy', $master))
            ->assertNotFound();

        $this->assertNotSoftDeleted('users', ['id' => $master->id]);
    }

    public function test_daftar_owner_menampilkan_hapus_hanya_untuk_akun_yatim(): void
    {
        $master = $this->master();
        $orphan = $this->admin('owner_yatim');
        $orphan->organizations()->detach();

        $member = $this->admin('admin_anggota');

        $pending = $this->admin('owner_pending');
        $pending->organizations()->detach();
        WorkspaceRequest::create([
            'requester_id' => $pending->id,
            'name' => 'Workspace Pending',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.owners.index'))
            ->assertOk()
            ->assertSee(route('master.owners.destroy', $orphan), false)
            ->assertDontSee(route('master.owners.destroy', $member), false)
            ->assertDontSee(route('master.owners.destroy', $pending), false)
            ->assertSee('Masih anggota workspace')
            ->assertSee('Permintaan pending');
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

    public function test_owner_dapat_mengajukan_workspace(): void
    {
        $owner = $this->admin('owner_alpha');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->withSession([
                'auth_token' => $owner->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.workspace.requests.store'), [
                'name' => 'Workspace Baru',
                'type' => 'education',
                'timezone' => 'Asia/Jakarta',
                'note' => 'Untuk kelas malam.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_requests', [
            'requester_id' => $owner->id,
            'name' => 'Workspace Baru',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => null,
            'actor_id' => $owner->id,
            'action' => 'workspace_requested',
        ]);
    }

    public function test_admin_bukan_owner_tidak_dapat_mengajukan_workspace(): void
    {
        $admin = $this->admin('admin_biasa');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($admin->id, [
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.workspace.requests.store'), [
                'name' => 'Workspace Baru',
                'type' => 'education',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden();
    }

    public function test_owner_melihat_riwayat_permintaan_workspace(): void
    {
        $owner = $this->admin('owner_alpha');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);
        WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Malam',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->withSession([
                'auth_token' => $owner->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->get(route('admin.workspace.index'))
            ->assertOk()
            ->assertSee('Workspace Malam')
            ->assertSee('Menunggu');
    }

    public function test_master_menyetujui_permintaan_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_alpha');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);
        $workspaceRequest = WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Baru',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.workspace-requests.approve.update', $workspaceRequest), [
                'slug' => 'workspace-baru',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.owners.index'));

        $workspaceRequest->refresh();
        $this->assertSame(WorkspaceRequest::STATUS_APPROVED, $workspaceRequest->status);
        $this->assertSame($master->id, $workspaceRequest->reviewed_by);
        $this->assertNotNull($workspaceRequest->organization_id);
        $this->assertTrue($workspaceRequest->organization->owners()->whereKey($owner->id)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $workspaceRequest->organization_id,
            'action' => 'workspace_request_approved',
        ]);
    }

    public function test_master_menolak_permintaan_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_alpha');
        $workspaceRequest = WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Baru',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.workspace-requests.reject', $workspaceRequest), [
                'rejection_reason' => 'Belum diperlukan saat ini.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.owners.index'));

        $workspaceRequest->refresh();
        $this->assertSame(WorkspaceRequest::STATUS_REJECTED, $workspaceRequest->status);
        $this->assertSame($master->id, $workspaceRequest->reviewed_by);
        $this->assertSame('Belum diperlukan saat ini.', $workspaceRequest->rejection_reason);
        $this->assertNull($workspaceRequest->organization_id);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => null,
            'action' => 'workspace_request_rejected',
        ]);
    }

    public function test_master_tidak_dapat_menyetujui_permintaan_dua_kali(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_alpha');
        $workspaceRequest = WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Baru',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.workspace-requests.approve.update', $workspaceRequest), [
                'slug' => 'workspace-baru',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.workspace-requests.approve.update', $workspaceRequest), [
                'slug' => 'workspace-lain',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(1, Organization::where('name', 'Workspace Baru')->count());
    }

    public function test_master_melihat_antrean_permintaan_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_alpha');
        $organization = $this->organization('alpha', 'Alpha');
        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);
        WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Malam',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.owners.index'))
            ->assertOk()
            ->assertSee('Workspace Malam')
            ->assertSee('owner_alpha')
            ->assertSee('Tinjau');
    }

    public function test_master_melihat_form_persetujuan_permintaan_workspace(): void
    {
        $master = $this->master();
        $owner = $this->admin('owner_alpha');
        $workspaceRequest = WorkspaceRequest::create([
            'requester_id' => $owner->id,
            'name' => 'Workspace Malam',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.workspace-requests.approve', $workspaceRequest))
            ->assertOk()
            ->assertSee('Workspace Malam')
            ->assertSee('workspace-malam');
    }

    public function test_admin_biasa_tidak_dapat_menyetujui_permintaan_workspace(): void
    {
        $admin = $this->admin('admin_biasa');
        $workspaceRequest = WorkspaceRequest::create([
            'requester_id' => $admin->id,
            'name' => 'Workspace Baru',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->put(route('master.workspace-requests.approve.update', $workspaceRequest), [
                'slug' => 'workspace-baru',
            ])
            ->assertForbidden();
    }

    public function test_owner_dengan_dua_workspace_melihat_pilihan_workspace(): void
    {
        $owner = $this->admin('owner_dua');
        $alpha = $this->organization('alpha', 'Alpha');
        $beta = $this->organization('beta', 'Beta');

        foreach ([$alpha, $beta] as $organization) {
            $organization->users()->attach($owner->id, [
                'role' => 'owner',
                'status' => 'active',
            ]);
        }

        $this->actingAs($owner)
            ->withSession([
                'auth_token' => $owner->current_session_token,
                'organization_id' => $alpha->id,
            ])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Workspace saya');

        $this->actingAs($owner)
            ->withSession([
                'auth_token' => $owner->current_session_token,
                'organization_id' => $alpha->id,
            ])
            ->post(route('organizations.switch'), ['organization_id' => $beta->id])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame($beta->id, session('organization_id'));
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
