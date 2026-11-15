<?php

namespace Tests\Feature;

use App\Events\PesertaStatusLogin;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\ParticipantLoginActivity;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnlinePesertaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_peserta_status_login_disiarkan_saat_peserta_login_dan_logout(): void
    {
        $events = collect();
        Event::listen(PesertaStatusLogin::class, function (PesertaStatusLogin $event) use ($events) {
            $events->push($event);
        });

        $peserta = User::create([
            'nama' => 'Yogi Testing',
            'username' => 'yogi_test',
            'password' => 'password123',
            'role' => 'peserta',
            'current_session_token' => 'token-session-123',
        ]);

        $organization = Organization::query()->firstOrFail();

        $this->post(route('organizations.login.store', $organization), [
            'username' => 'yogi_test',
            'password' => 'password123',
        ])->assertRedirect(route('peserta.dashboard'));

        $this->assertDatabaseHas('participant_login_activities', [
            'user_id' => $peserta->id,
            'status' => 'login',
        ]);
        $loginEvent = $events->firstWhere('status', 'login');
        $this->assertNotNull($loginEvent);
        $this->assertSame($peserta->id, $loginEvent->userId);
        $this->assertSame($organization->id, $loginEvent->organizationId);
        $this->assertSame('login', $loginEvent->status);
        $this->assertNotNull(OrganizationMembership::where('user_id', $peserta->id)->value('last_seen_at'));

        $peserta = $peserta->fresh();
        $token = $peserta->current_session_token;
        $resLogout = $this->actingAs($peserta)
            ->withSession(['auth_token' => $token, 'organization_id' => $organization->id])
            ->post(route('logout'));

        $resLogout->assertRedirect(route('organizations.login', $organization));

        $logoutEvent = $events->firstWhere('status', 'logout');
        $this->assertNotNull($logoutEvent);
        $this->assertSame($peserta->id, $logoutEvent->userId);
        $this->assertDatabaseHas('participant_login_activities', [
            'user_id' => $peserta->id,
            'status' => 'logout',
        ]);
        $this->assertNull(OrganizationMembership::where('user_id', $peserta->id)->value('last_seen_at'));
    }

    public function test_admin_bisa_mengakses_endpoint_peserta_online(): void
    {
        $admin = User::create([
            'nama' => 'Admin Test',
            'username' => 'admin_test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-token',
        ]);

        $peserta = User::create([
            'nama' => 'Siswa Online',
            'username' => 'siswa_online',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-online-token',
        ]);

        DB::table('organization_memberships')
            ->where('user_id', $peserta->id)
            ->update(['last_seen_at' => now()]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->getJson(route('admin.dashboard.peserta-online'));

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(1, $data['total']);
        $this->assertSame('Siswa Online', $data['peserta'][0]['nama']);
        $this->assertSame('siswa_online', $data['peserta'][0]['username']);
        $this->assertArrayHasKey('last_activity_at', $data['peserta'][0]);
        $this->assertArrayHasKey('activities', $data);
        $this->assertArrayHasKey('server_now', $data);
    }

    public function test_logout_paksa_langsung_menghapus_presence(): void
    {
        $organization = Organization::query()->firstOrFail();
        $peserta = User::create([
            'nama' => 'Peserta Paksa',
            'username' => 'peserta_logout_paksa',
            'password' => Hash::make('password'),
            'role' => 'peserta',
        ]);
        DB::table('organization_memberships')
            ->where('user_id', $peserta->id)
            ->update(['last_seen_at' => now()]);
        app(OrganizationContext::class)->force($organization);

        ParticipantLoginActivity::recordAndBroadcast($peserta, 'logout', 'fullscreen_violation');

        $this->assertNull(OrganizationMembership::where('user_id', $peserta->id)->value('last_seen_at'));
        $this->assertDatabaseHas('participant_login_activities', [
            'user_id' => $peserta->id,
            'status' => 'logout',
            'reason' => 'fullscreen_violation',
        ]);
    }

    public function test_heartbeat_dibatasi_tiga_puluh_detik_dan_kedaluwarsa_setelah_dua_menit(): void
    {
        $organization = Organization::query()->firstOrFail();
        $peserta = User::create([
            'nama' => 'Peserta Heartbeat',
            'username' => 'peserta_heartbeat',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'heartbeat-token',
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'heartbeat-token', 'organization_id' => $organization->id])
            ->get(route('peserta.dashboard'))
            ->assertOk();

        $pertama = OrganizationMembership::where('user_id', $peserta->id)->firstOrFail()->last_seen_at;
        $this->travel(20)->seconds();

        $this->get(route('peserta.dashboard'))->assertOk();
        $this->assertEquals($pertama, OrganizationMembership::where('user_id', $peserta->id)->firstOrFail()->last_seen_at);

        $this->travel(11)->seconds();
        $this->get(route('peserta.dashboard'))->assertOk();
        $this->assertTrue(
            OrganizationMembership::where('user_id', $peserta->id)->firstOrFail()->last_seen_at->greaterThan($pertama)
        );

        DB::table('organization_memberships')
            ->where('user_id', $peserta->id)
            ->update(['last_seen_at' => now()->subMinutes(3)]);

        $admin = User::create([
            'nama' => 'Admin Presence',
            'username' => 'admin_presence',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-presence-token',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-presence-token', 'organization_id' => $organization->id])
            ->getJson(route('admin.dashboard.peserta-online'))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_total_online_tidak_terpotong_oleh_batas_daftar_dua_puluh(): void
    {
        $organization = Organization::query()->firstOrFail();
        $admin = User::create([
            'nama' => 'Admin Total',
            'username' => 'admin_total',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-total-token',
        ]);

        foreach (range(1, 21) as $nomor) {
            $peserta = User::create([
                'nama' => 'Peserta '.$nomor,
                'username' => 'peserta_total_'.$nomor,
                'password' => Hash::make('password'),
                'role' => 'peserta',
            ]);
            DB::table('organization_memberships')
                ->where('organization_id', $organization->id)
                ->where('user_id', $peserta->id)
                ->update(['last_seen_at' => now()->subSeconds($nomor)]);
        }

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-total-token', 'organization_id' => $organization->id])
            ->getJson(route('admin.dashboard.peserta-online'))
            ->assertOk()
            ->assertJsonPath('total', 21);

        $this->assertCount(20, $response->json('peserta'));
    }

    public function test_presence_dan_channel_dashboard_terisolasi_per_workspace(): void
    {
        $workspaceAktif = Organization::query()->firstOrFail();
        $workspaceLain = Organization::create([
            'name' => 'Workspace Lain',
            'slug' => 'workspace-lain-presence',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        $admin = User::create([
            'nama' => 'Admin Isolasi',
            'username' => 'admin_isolasi_presence',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-isolasi-token',
        ]);
        $pesertaLain = User::create([
            'nama' => 'Peserta Workspace Lain',
            'username' => 'peserta_workspace_lain',
            'password' => Hash::make('password'),
            'role' => 'peserta',
        ]);
        $workspaceAktif->users()->attach($admin->id, [
            'role' => 'admin',
            'status' => 'active',
        ]);
        $workspaceLain->users()->attach($pesertaLain->id, [
            'role' => 'participant',
            'status' => 'active',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-isolasi-token', 'organization_id' => $workspaceAktif->id])
            ->getJson(route('admin.dashboard.peserta-online'))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonMissing(['username' => 'peserta_workspace_lain']);

        $event = new PesertaStatusLogin(
            $workspaceLain->id,
            $pesertaLain->id,
            $pesertaLain->nama,
            $pesertaLain->username,
            'login',
            now()->toISOString(),
        );

        $this->assertSame(
            'private-admin-dashboard.'.$workspaceLain->id,
            $event->broadcastOn()[0]->name
        );

        $this->assertFalse(
            $admin->organizations()->whereKey($workspaceLain->id)->wherePivot('status', 'active')->exists()
        );
    }

    public function test_dashboard_menampilkan_status_sinkron_dan_aktivitas_terakhir(): void
    {
        $organization = Organization::query()->firstOrFail();
        $admin = User::create([
            'nama' => 'Admin Tampilan',
            'username' => 'admin_tampilan_presence',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-tampilan-token',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-tampilan-token', 'organization_id' => $organization->id])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Terakhir sinkron')
            ->assertSee('Aktif sekarang')
            ->assertSee('admin-dashboard.\' + config.organizationId', false)
            ->assertSee('x-text="totalOnline"', false);
    }

    public function test_peserta_tidak_bisa_mengakses_endpoint_peserta_online(): void
    {
        $peserta = User::create([
            'nama' => 'Peserta Biasa',
            'username' => 'peserta_biasa',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);

        $response = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->getJson(route('admin.dashboard.peserta-online'));

        $response->assertForbidden();
    }
}
