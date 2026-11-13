<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAccessAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_memiliki_route_login_terpisah(): void
    {
        $this->get(route('master.login'))
            ->assertOk()
            ->assertSee('Akses master platform')
            ->assertSee(route('master.login.store'));
    }

    public function test_master_ditolak_dari_login_pengguna_dan_dapat_masuk_dari_route_master(): void
    {
        $master = $this->master();

        $this->post(route('organizations.login.store', Organization::query()->firstOrFail()), [
            'username' => $master->username,
            'password' => 'password123',
        ])->assertRedirect(route('master.login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post(route('master.login.store'), [
            'username' => $master->username,
            'password' => 'password123',
        ])->assertRedirect(route('master.organizations.index'));
        $this->assertAuthenticatedAs($master);

        $this->post(route('logout'))->assertRedirect(route('master.login'));
        $this->assertGuest();
    }

    public function test_admin_organisasi_tidak_dapat_masuk_melalui_route_master(): void
    {
        $admin = User::create([
            'nama' => 'Admin Organisasi',
            'username' => 'admin_organisasi',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->post(route('master.login.store'), [
            'username' => $admin->username,
            'password' => 'password123',
        ])->assertRedirect(route('master.login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_maintenance_memblokir_pengguna_tetapi_master_tetap_dapat_membukanya(): void
    {
        $master = $this->master();
        $participant = User::create([
            'nama' => 'Peserta Maintenance',
            'username' => 'peserta_maintenance',
            'password' => 'password123',
            'role' => 'peserta',
            'current_session_token' => 'participant-token',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.maintenance.update'), [
                'maintenance_enabled' => true,
                'maintenance_message' => 'Pembaruan server berlangsung sampai selesai.',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $settings = AppSetting::current();
        $this->assertTrue($settings->maintenance_enabled);
        $this->assertNotNull($settings->maintenance_started_at);

        auth()->logout();
        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300')
            ->assertSee('Pembaruan server berlangsung sampai selesai.');
        $this->get(route('master.login'))
            ->assertOk()
            ->assertSee('Maintenance aktif');

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token])
            ->get(route('peserta.dashboard'))
            ->assertStatus(503);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.organizations.index'))
            ->assertOk()
            ->assertSee('Maintenance aktif');

        $this->put(route('master.maintenance.update'), [
            'maintenance_enabled' => false,
            'maintenance_message' => 'Pembaruan server selesai.',
        ])->assertRedirect();

        $this->assertFalse(AppSetting::current()->maintenance_enabled);
        $this->assertNull(AppSetting::current()->maintenance_started_at);
    }

    public function test_master_dapat_menangguhkan_dan_mengaktifkan_kembali_organisasi(): void
    {
        $organization = Organization::query()->firstOrFail();
        $master = $this->master();
        $admin = User::create([
            'nama' => 'Admin Ditangguhkan',
            'username' => 'admin_ditangguhkan',
            'password' => 'password123',
            'role' => 'admin',
            'current_session_token' => 'suspended-admin-token',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.organizations.status.update', $organization), ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($organization->fresh()->is_active);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->get(route('admin.dashboard'))
            ->assertForbidden();
        auth()->logout();
        $this->get(route('organizations.login', $organization))->assertNotFound();
        $this->post(route('organizations.login.store', $organization), [
            'username' => $admin->username,
            'password' => 'password123',
        ])->assertNotFound();
        $this->assertGuest();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->put(route('master.organizations.status.update', $organization), ['is_active' => true])
            ->assertRedirect();

        $this->assertTrue($organization->fresh()->is_active);
    }

    public function test_master_tanpa_workspace_diarahkan_ke_halaman_organisasi_dari_menu_workspace(): void
    {
        $master = $this->master();
        $master->organizations()->detach();

        foreach ([
            'admin.participants.index',
            'admin.quizzes.index',
            'admin.classes.index',
        ] as $route) {
            $this->actingAs($master)
                ->withSession(['auth_token' => $master->current_session_token])
                ->get(route($route))
                ->assertRedirect(route('master.organizations.index'))
                ->assertSessionHasErrors('workspace');
        }
    }

    public function test_master_tanpa_workspace_melihat_menu_tautkan_peserta_tanpa_menu_workspace(): void
    {
        $master = $this->master();
        $master->organizations()->detach();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.organizations.index'))
            ->assertOk()
            ->assertSee('Tautkan peserta')
            ->assertSee(route('master.participants.index'))
            ->assertDontSee(route('admin.participants.index'))
            ->assertDontSee(route('admin.quizzes.index'))
            ->assertDontSee(route('admin.classes.index'));
    }

    private function master(): User
    {
        return User::create([
            'nama' => 'Master Platform',
            'username' => 'master_platform',
            'password' => 'password123',
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-token',
        ]);
    }
}
