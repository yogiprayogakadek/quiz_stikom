<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParticipantLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_baru_dibuat_admin_langsung_terverifikasi(): void
    {
        $organization = $this->defaultOrganization();
        $admin = $this->makeAdmin($organization, 'admin_verifikasi');

        $this->actingAsMember($admin, $organization)
            ->post(route('admin.participants.store'), [
                'nama' => 'Peserta Baru',
                'username' => 'peserta_baru_verifikasi',
                'email' => 'peserta-baru@example.test',
            ])
            ->assertRedirect(route('admin.participants.index'));

        $peserta = User::query()->where('username', 'peserta_baru_verifikasi')->firstOrFail();

        $this->assertNotNull($peserta->email_verified_at);
        $this->assertTrue($peserta->hasVerifiedEmail());
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $peserta->id,
            'role' => 'participant',
            'status' => 'active',
        ]);
    }

    public function test_admin_dapat_menautkan_akun_existing_dengan_nama_cocok(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $admin = $this->makeAdmin($workspaceBaru, 'admin_tautkan');
        $peserta = $this->makeParticipant($workspaceAsal, 'nim123', 'Budi Santoso', 'budi@example.test', true);
        $passwordLama = $peserta->password;
        $jumlahUser = User::withTrashed()->count();

        $this->actingAsMember($admin, $workspaceBaru)
            ->from(route('admin.participants.create'))
            ->post(route('admin.participants.store'), [
                'nama' => 'Budi Santoso',
                'username' => 'nim123',
            ])
            ->assertRedirect(route('admin.participants.create'))
            ->assertSessionHas('participant_link_candidate');

        $this->get(route('admin.participants.create'))
            ->assertOk()
            ->assertSee('Akun sudah terdaftar')
            ->assertSee('Budi Santoso');

        $this->assertSame($jumlahUser, User::withTrashed()->count());
        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
        ]);

        $this->post(route('admin.participants.link'))
            ->assertRedirect(route('admin.participants.index'))
            ->assertSessionHas('sukses');

        $this->assertSame($jumlahUser, User::withTrashed()->count());
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
            'role' => 'participant',
            'status' => 'active',
        ]);
        $this->assertSame(1, $workspaceBaru->memberships()
            ->where('role', 'participant')
            ->where('status', 'active')
            ->count());
        $this->assertSame($passwordLama, $peserta->fresh()->password);
        $this->assertSame('Budi Santoso', $peserta->fresh()->nama);
        $this->assertSame('budi@example.test', $peserta->fresh()->email);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'participant_linked',
            'subject_type' => 'user',
            'subject_id' => $peserta->id,
            'organization_id' => $workspaceBaru->id,
        ]);
    }

    public function test_admin_tidak_dapat_menautkan_akun_dengan_nama_berbeda(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $admin = $this->makeAdmin($workspaceBaru, 'admin_tautkan');
        $peserta = $this->makeParticipant($workspaceAsal, 'nim123', 'Budi Santoso', 'budi@example.test', true);

        $this->actingAsMember($admin, $workspaceBaru)
            ->from(route('admin.participants.create'))
            ->post(route('admin.participants.store'), [
                'nama' => 'Budi Lain',
                'username' => 'nim123',
                'email' => 'budi@example.test',
            ])
            ->assertRedirect(route('admin.participants.create'))
            ->assertSessionHasErrors([
                'username' => 'NIM/username ini sudah terdaftar dengan nama berbeda. Periksa ejaan nama, gunakan nama sesuai akun, atau minta master platform menautkan dengan konfirmasi nama berbeda.',
            ]);

        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
        ]);
    }

    public function test_admin_mendapat_info_jika_peserta_sudah_ada_di_workspace(): void
    {
        $workspace = $this->defaultOrganization();
        $admin = $this->makeAdmin($workspace, 'admin_sudah_ada');
        $this->makeParticipant($workspace, 'nim456', 'Siti Aminah', 'siti@example.test', true);

        $this->actingAsMember($admin, $workspace)
            ->from(route('admin.participants.create'))
            ->post(route('admin.participants.store'), [
                'nama' => 'Siti Aminah',
                'username' => 'nim456',
                'email' => 'siti@example.test',
            ])
            ->assertRedirect(route('admin.participants.index'))
            ->assertSessionHas('sukses', 'Peserta Siti Aminah sudah ada di workspace ini.');
    }

    public function test_tautan_peserta_ditolak_saat_kuota_workspace_penuh(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $workspaceBaru->update(['participant_limit' => 1]);
        $admin = $this->makeAdmin($workspaceBaru, 'admin_kuota');
        $this->makeParticipant($workspaceBaru, 'nim_penuh', 'Peserta Penuh', 'penuh@example.test', true);
        $peserta = $this->makeParticipant($workspaceAsal, 'nim_tautan', 'Peserta Tautan', 'tautan@example.test', true);

        $this->actingAsMember($admin, $workspaceBaru)
            ->from(route('admin.participants.create'))
            ->post(route('admin.participants.store'), [
                'nama' => 'Peserta Tautan',
                'username' => 'nim_tautan',
            ])
            ->assertRedirect(route('admin.participants.create'))
            ->assertSessionHasErrors('nama');

        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
        ]);
    }

    public function test_impor_menautkan_akun_existing_dengan_nama_cocok(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $admin = $this->makeAdmin($workspaceBaru, 'admin_impor');
        $peserta = $this->makeParticipant($workspaceAsal, 'nim789', 'Raka Pratama', 'raka@example.test', true);
        $passwordLama = $peserta->password;
        $jumlahUser = User::withTrashed()->count();
        $file = UploadedFile::fake()->createWithContent('peserta.csv', "nama,username,password\nRaka Pratama,nim789,\n");

        $this->actingAsMember($admin, $workspaceBaru)
            ->post(route('admin.participants.import.store'), ['file' => $file])
            ->assertRedirect(route('admin.participants.import.result'));

        $hasil = session('impor_hasil');

        $this->assertSame(1, $hasil['ringkasan']['berhasil']);
        $this->assertSame(0, $hasil['ringkasan']['gagal']);
        $this->assertStringContainsString('ditautkan', $hasil['ringkasan']['baris'][0]['alasan']);
        $this->assertNull($hasil['ringkasan']['baris'][0]['password']);
        $this->assertSame($jumlahUser, User::withTrashed()->count());
        $this->assertSame($passwordLama, $peserta->fresh()->password);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
            'role' => 'participant',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'participant_linked',
            'subject_type' => 'user',
            'subject_id' => $peserta->id,
            'organization_id' => $workspaceBaru->id,
        ]);
    }

    public function test_impor_menolak_akun_existing_dengan_nama_berbeda(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $admin = $this->makeAdmin($workspaceBaru, 'admin_impor');
        $peserta = $this->makeParticipant($workspaceAsal, 'nim789', 'Raka Pratama', 'raka@example.test', true);
        $file = UploadedFile::fake()->createWithContent('peserta.csv', "nama,username\nRaka Lain,nim789\n");

        $this->actingAsMember($admin, $workspaceBaru)
            ->post(route('admin.participants.import.store'), ['file' => $file])
            ->assertRedirect(route('admin.participants.import.result'));

        $hasil = session('impor_hasil');

        $this->assertSame(0, $hasil['ringkasan']['berhasil']);
        $this->assertSame(1, $hasil['ringkasan']['gagal']);
        $this->assertStringContainsString('nama berbeda', $hasil['ringkasan']['baris'][0]['alasan']);
        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
        ]);
    }

    public function test_admin_tidak_dapat_mengubah_atau_mereset_akun_multi_workspace(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $admin = $this->makeAdmin($workspaceBaru, 'admin_shared');
        $peserta = $this->makeParticipant($workspaceAsal, 'nim999', 'Dewi Lestari', 'dewi@example.test', true);
        $workspaceBaru->users()->syncWithoutDetaching([
            $peserta->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        $this->actingAsMember($admin, $workspaceBaru)
            ->put(route('admin.participants.update', $peserta), [
                'nama' => 'Dewi Diubah',
                'username' => 'nim999',
                'email' => 'dewi@example.test',
            ])
            ->assertForbidden();

        $this->post(route('admin.participants.reset-password', $peserta))
            ->assertForbidden();

        $this->actingAsMember($admin, $workspaceBaru)
            ->get(route('admin.participants.edit', $peserta))
            ->assertOk()
            ->assertSee('Akun terhubung ke beberapa workspace');

        $this->assertSame('Dewi Lestari', $peserta->fresh()->nama);
    }

    public function test_admin_dapat_memverifikasi_peserta_satu_workspace(): void
    {
        $workspace = $this->defaultOrganization();
        $admin = $this->makeAdmin($workspace, 'admin_verif_satu');
        $peserta = $this->makeParticipant($workspace, 'nim_verif', 'Peserta Verifikasi', 'verif@example.test');

        $this->actingAsMember($admin, $workspace)
            ->post(route('admin.participants.verify', $peserta))
            ->assertRedirect();

        $this->assertNotNull($peserta->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'participant_email_verified',
            'subject_type' => 'user',
            'subject_id' => $peserta->id,
            'organization_id' => $workspace->id,
        ]);
    }

    public function test_admin_tidak_dapat_memverifikasi_akun_multi_workspace(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $admin = $this->makeAdmin($workspaceBaru, 'admin_verif_shared');
        $peserta = $this->makeParticipant($workspaceAsal, 'nim_shared', 'Peserta Shared', 'shared@example.test');
        $workspaceBaru->users()->syncWithoutDetaching([
            $peserta->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        $this->actingAsMember($admin, $workspaceBaru)
            ->post(route('admin.participants.verify', $peserta))
            ->assertForbidden();

        $this->assertNull($peserta->fresh()->email_verified_at);
    }

    public function test_master_dapat_menautkan_nama_berbeda_dan_memverifikasi(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $master = $this->makeMaster();
        $peserta = $this->makeParticipant($workspaceAsal, 'nim_master', 'Budi Santoso', 'master@example.test');

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->post(route('master.participants.link'), [
                'organization_id' => $workspaceBaru->id,
                'username' => 'nim_master',
                'nama' => 'Budi Santoso Baru',
                'allow_name_mismatch' => '1',
            ])
            ->assertRedirect(route('master.participants.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
            'role' => 'participant',
            'status' => 'active',
        ]);
        $this->assertNotNull($peserta->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'participant_linked',
            'subject_type' => 'user',
            'subject_id' => $peserta->id,
            'organization_id' => $workspaceBaru->id,
        ]);
    }

    public function test_master_tetap_menolak_nama_berbeda_tanpa_konfirmasi(): void
    {
        $workspaceAsal = $this->defaultOrganization();
        $workspaceBaru = $this->makeOrganization('workspace-baru');
        $master = $this->makeMaster();
        $peserta = $this->makeParticipant($workspaceAsal, 'nim_master', 'Budi Santoso', 'master@example.test');

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->post(route('master.participants.link'), [
                'organization_id' => $workspaceBaru->id,
                'username' => 'nim_master',
                'nama' => 'Budi Santoso Baru',
            ])
            ->assertSessionHasErrors('nama');

        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $workspaceBaru->id,
            'user_id' => $peserta->id,
        ]);
    }

    public function test_master_dapat_memverifikasi_akun_tanpa_menautkan(): void
    {
        $workspace = $this->defaultOrganization();
        $master = $this->makeMaster();
        $peserta = $this->makeParticipant($workspace, 'nim_verify_master', 'Peserta Master', 'master-verify@example.test');

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->post(route('master.participants.verify'), [
                'username' => 'nim_verify_master',
            ])
            ->assertRedirect(route('master.participants.index'))
            ->assertSessionHas('sukses');

        $this->assertNotNull($peserta->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'participant_email_verified',
            'subject_type' => 'user',
            'subject_id' => $peserta->id,
        ]);
    }

    public function test_daftar_peserta_menampilkan_status_verifikasi(): void
    {
        $workspace = $this->defaultOrganization();
        $admin = $this->makeAdmin($workspace, 'admin_index');
        $this->makeParticipant($workspace, 'nim_index', 'Peserta Index', 'index@example.test');

        $this->actingAsMember($admin, $workspace)
            ->get(route('admin.participants.index'))
            ->assertOk()
            ->assertSee('Belum verifikasi')
            ->assertSee('Verifikasi');
    }

    public function test_master_verify_menaruh_error_di_form_verifikasi(): void
    {
        $this->defaultOrganization();
        $master = $this->makeMaster();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->from(route('master.participants.index'))
            ->post(route('master.participants.verify'), [
                'username' => 'nim_tidak_ada',
            ])
            ->assertRedirect(route('master.participants.index'))
            ->assertSessionHasErrors('username', null, 'verify');
    }

    public function test_master_dapat_membuka_halaman_tautkan_peserta(): void
    {
        $master = $this->makeMaster();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.participants.index'))
            ->assertOk()
            ->assertSee('Tautkan akun ke workspace')
            ->assertSee('Verifikasi akun');
    }

    private function defaultOrganization(): Organization
    {
        return Organization::query()->firstOrFail();
    }

    private function makeOrganization(string $slug): Organization
    {
        return Organization::create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function makeAdmin(Organization $organization, string $username): User
    {
        $admin = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => $username.'-token',
        ]);

        $organization->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        return $admin;
    }

    private function makeParticipant(
        Organization $organization,
        string $username,
        string $nama,
        ?string $email = null,
        bool $verified = false,
    ): User {
        $participant = User::create([
            'nama' => $nama,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => $username.'-token',
        ]);

        $organization->users()->syncWithoutDetaching([
            $participant->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        if ($verified) {
            $participant->forceFill(['email_verified_at' => now()])->save();
        }

        return $participant;
    }

    private function makeMaster(): User
    {
        return User::create([
            'nama' => 'Master Platform',
            'username' => 'master_platform',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-platform-token',
        ]);
    }

    private function actingAsMember(User $user, Organization $organization): self
    {
        return $this->actingAs($user)->withSession([
            'auth_token' => $user->current_session_token,
            'organization_id' => $organization->id,
        ]);
    }
}
