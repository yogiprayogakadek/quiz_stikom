<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_pendaftaran_dapat_dibuka(): void
    {
        $this->get(route('register.organization'))
            ->assertOk()
            ->assertSee('Daftarkan organisasi')
            ->assertSee('Syarat Penggunaan');
    }

    public function test_pendaftaran_membuat_organisasi_owner_dan_mengirim_verifikasi(): void
    {
        Notification::fake();

        $this->post(route('register.organization.store'), $this->payload([
            'name' => 'Workspace Alpha',
            'slug' => 'workspace-alpha',
            'owner_username' => 'owner_alpha',
            'owner_email' => 'owner-alpha@example.test',
        ]))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('sukses');

        $organization = Organization::where('slug', 'workspace-alpha')->firstOrFail();
        $owner = User::where('username', 'owner_alpha')->firstOrFail();

        $this->assertTrue($organization->is_active);
        $this->assertNull($owner->email_verified_at);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertAuthenticatedAs($owner);
        Notification::assertSentTo($owner, VerifyEmail::class);
    }

    public function test_pendaftaran_menolak_slug_username_dan_email_duplikat(): void
    {
        Organization::create([
            'name' => 'Lama',
            'slug' => 'workspace-lama',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        User::create([
            'nama' => 'Lama',
            'username' => 'owner_lama',
            'email' => 'owner-lama@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->post(route('register.organization.store'), $this->payload([
            'name' => 'Baru',
            'slug' => 'workspace-lama',
            'owner_username' => 'owner_lama',
            'owner_email' => 'owner-lama@example.test',
        ]))->assertSessionHasErrors(['slug', 'owner_username', 'owner_email']);
    }

    public function test_pendaftaran_menolak_tanpa_persetujuan_syarat(): void
    {
        $this->post(route('register.organization.store'), $this->payload([
            'terms' => null,
        ]))->assertSessionHasErrors('terms');
    }

    public function test_honeypot_tidak_membuat_organisasi(): void
    {
        $this->post(route('register.organization.store'), $this->payload([
            'slug' => 'workspace-bot',
            'website' => 'https://spam.example',
        ]))->assertRedirect(route('login'));

        $this->assertDatabaseMissing('organizations', ['slug' => 'workspace-bot']);
    }

    public function test_rate_limit_pendaftaran(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post(route('register.organization.store'), [
                'name' => '',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_owner_baru_tidak_masuk_ke_organisasi_lain(): void
    {
        $lain = Organization::create([
            'name' => 'Organisasi Lain',
            'slug' => 'organisasi-lain',
            'type' => 'community',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->post(route('register.organization.store'), $this->payload([
            'slug' => 'workspace-baru',
            'owner_username' => 'owner_baru',
            'owner_email' => 'owner-baru@example.test',
        ]))->assertRedirect();

        $owner = User::where('username', 'owner_baru')->firstOrFail();
        $organization = Organization::where('slug', 'workspace-baru')->firstOrFail();

        $this->assertSame(1, $owner->organizations()->count());
        $this->assertSame($organization->id, $owner->organizations()->first()->id);
        $this->assertNotSame($lain->id, $organization->id);
    }

    public function test_owner_belum_verifikasi_diarahkan_ke_halaman_verifikasi(): void
    {
        $this->post(route('register.organization.store'), $this->payload([
            'slug' => 'workspace-verifikasi',
            'owner_username' => 'owner_verifikasi',
            'owner_email' => 'owner-verifikasi@example.test',
        ]))->assertRedirect(route('verification.notice'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Workspace Uji',
            'slug' => 'workspace-uji',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'owner_name' => 'Owner Uji',
            'owner_username' => 'owner_uji',
            'owner_email' => 'owner-uji@example.test',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'terms' => '1',
        ], $override);
    }
}
