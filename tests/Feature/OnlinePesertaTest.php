<?php

namespace Tests\Feature;

use App\Events\PesertaStatusLogin;
use App\Models\User;
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
        Event::fake([PesertaStatusLogin::class]);

        $peserta = User::create([
            'nama' => 'Yogi Testing',
            'username' => 'yogi_test',
            'password' => 'password123',
            'role' => 'peserta',
            'current_session_token' => 'token-session-123',
        ]);

        $this->post(route('login.store'), [
            'username' => 'yogi_test',
            'password' => 'password123',
        ]);

        Event::assertDispatched(PesertaStatusLogin::class, function (PesertaStatusLogin $event) use ($peserta) {
            return $event->userId === $peserta->id && $event->status === 'login';
        });
        $this->assertDatabaseHas('participant_login_activities', [
            'user_id' => $peserta->id,
            'status' => 'login',
        ]);

        // Logout
        $resLogout = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-session-123'])
            ->post(route('logout'));

        $resLogout->assertRedirect(route('login'));

        Event::assertDispatched(PesertaStatusLogin::class, function (PesertaStatusLogin $event) use ($peserta) {
            return $event->userId === $peserta->id && $event->status === 'logout';
        });
        $this->assertDatabaseHas('participant_login_activities', [
            'user_id' => $peserta->id,
            'status' => 'logout',
        ]);
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

        // Insert session record
        DB::table('sessions')->insert([
            'id' => 'test-session-id-123',
            'user_id' => $peserta->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'dummy',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->getJson(route('admin.dashboard.peserta-online'));

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(1, $data['total']);
        $this->assertSame('Siswa Online', $data['peserta'][0]['nama']);
        $this->assertSame('siswa_online', $data['peserta'][0]['username']);
        $this->assertArrayHasKey('activities', $data);
        $this->assertArrayHasKey('server_now', $data);
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
