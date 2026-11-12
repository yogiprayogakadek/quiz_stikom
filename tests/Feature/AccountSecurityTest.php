<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_lama_tanpa_email_tetap_bisa_masuk(): void
    {
        $this->organization();
        $admin = $this->user('admin_lama', 'admin');

        $this->assertTrue($admin->hasVerifiedEmail());

        $this->post(route('organizations.login.store', $this->organization()), [
            'username' => $admin->username,
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin->fresh());
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_akun_dengan_email_belum_verifikasi_diarahkan_ke_halaman_verifikasi(): void
    {
        $this->organization();
        $admin = $this->user('admin_belum_verifikasi', 'admin', 'belum@example.test');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($admin->email);
    }

    public function test_email_terverifikasi_tidak_diblokir(): void
    {
        $this->organization();
        $admin = $this->user('admin_terverifikasi', 'admin', 'terverifikasi@example.test', true);

        $this->assertNotNull($admin->fresh()->email_verified_at);
        $this->assertTrue($admin->organizations()->wherePivot('status', 'active')->exists());

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_tautan_verifikasi_menandai_email_terverifikasi(): void
    {
        $this->organization();
        $admin = $this->user('admin_verifikasi', 'admin', 'verifikasi@example.test');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $admin->id,
            'hash' => sha1($admin->email),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get($url)
            ->assertRedirect(route('admin.dashboard'));

        $this->assertNotNull($admin->fresh()->email_verified_at);
    }

    public function test_verifikasi_email_dapat_dikirim_ulang(): void
    {
        Notification::fake();
        $this->organization();
        $admin = $this->user('admin_kirim_ulang', 'admin', 'kirim-ulang@example.test');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($admin, VerifyEmail::class);
    }

    public function test_mengubah_email_mereset_verifikasi(): void
    {
        $user = $this->user('admin_ganti_email', 'admin', 'lama@example.test', true);

        $user->email = 'baru@example.test';
        $user->save();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_peserta_baru_dari_admin_wajib_memiliki_email_unik(): void
    {
        $organization = $this->organization();
        $admin = $this->user('admin_peserta', 'admin', 'admin-peserta@example.test', true);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.participants.store'), [
                'nama' => 'Peserta Tanpa Email',
                'username' => 'peserta_tanpa_email',
            ])
            ->assertSessionHasErrors('email');

        $this->post(route('admin.participants.store'), [
            'nama' => 'Peserta Baru',
            'username' => 'peserta_baru',
            'email' => 'peserta@example.test',
        ])->assertRedirect(route('admin.participants.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'peserta_baru',
            'email' => 'peserta@example.test',
        ]);

        $this->post(route('admin.participants.store'), [
            'nama' => 'Peserta Duplikat',
            'username' => 'peserta_duplikat',
            'email' => 'peserta@example.test',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_dapat_menambahkan_email_ke_peserta_lama(): void
    {
        $organization = $this->organization();
        $admin = $this->user('admin_tambah_email', 'admin', 'admin-tambah@example.test', true);
        $participant = $this->user('peserta_lama', 'peserta');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $organization->id])
            ->put(route('admin.participants.update', $participant), [
                'nama' => $participant->nama,
                'username' => $participant->username,
                'email' => 'peserta-lama@example.test',
            ])
            ->assertRedirect(route('admin.participants.index'));

        $this->assertDatabaseHas('users', [
            'id' => $participant->id,
            'email' => 'peserta-lama@example.test',
        ]);
    }

    public function test_admin_baru_dari_owner_wajib_memiliki_email(): void
    {
        $organization = $this->organization();
        $owner = $this->user('owner_email', 'admin');
        $organization->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner', 'status' => 'active']]);

        $this->actingAs($owner)
            ->withSession(['auth_token' => $owner->current_session_token, 'organization_id' => $organization->id])
            ->post(route('admin.team.store'), [
                'nama' => 'Admin Tanpa Email',
                'username' => 'admin_tanpa_email',
            ])
            ->assertSessionHasErrors('email');

        $this->post(route('admin.team.store'), [
            'nama' => 'Admin Baru',
            'username' => 'admin_baru',
            'email' => 'admin-baru@example.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'username' => 'admin_baru',
            'email' => 'admin-baru@example.test',
        ]);
    }

    public function test_owner_baru_dari_master_wajib_memiliki_email(): void
    {
        $master = $this->user('master_email', 'admin', 'master@example.test', true);
        $master->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->post(route('master.organizations.store'), [
                'name' => 'Organisasi Tanpa Email',
                'slug' => 'organisasi-tanpa-email',
                'type' => 'education',
                'timezone' => 'Asia/Jakarta',
                'owner_name' => 'Owner Tanpa Email',
                'owner_username' => 'owner_tanpa_email',
            ])
            ->assertSessionHasErrors('owner_email');

        $this->post(route('master.organizations.store'), [
            'name' => 'Organisasi Baru',
            'slug' => 'organisasi-baru',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'owner_name' => 'Owner Baru',
            'owner_username' => 'owner_baru',
            'owner_email' => 'owner-baru@example.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'username' => 'owner_baru',
            'email' => 'owner-baru@example.test',
        ]);
    }

    public function test_halaman_lupa_dan_reset_password_dapat_dibuka(): void
    {
        $user = $this->user('reset_tampilan', 'admin', 'reset-tampilan@example.test', true);
        $token = PasswordBroker::broker()->createToken($user);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Lupa password?')
            ->assertSee(route('password.email'));

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_lupa_password_mengirim_tautan_untuk_email_terdaftar(): void
    {
        Notification::fake();
        $user = $this->user('reset_kirim', 'admin', 'reset-kirim@example.test', true);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_memakai_token_dan_mencabut_sesi_lama(): void
    {
        $user = $this->user('reset_token', 'admin', 'reset-token@example.test', true);
        $user->forceFill([
            'must_change_password' => true,
            'current_session_token' => 'sesi-lama',
        ])->save();
        $token = PasswordBroker::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password456',
            'password_confirmation' => 'password456',
        ])->assertRedirect(route('organizations.login', $this->organization()))
            ->assertSessionHas('sukses');

        $user->refresh();
        $this->assertTrue(Hash::check('password456', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->current_session_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    private function organization(): Organization
    {
        return Organization::query()->firstOrFail();
    }

    private function user(string $username, string $role, ?string $email = null, bool $verified = false): User
    {
        $user = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);

        if ($verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
