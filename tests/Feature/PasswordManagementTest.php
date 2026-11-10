<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_mengganti_password_dengan_password_saat_ini(): void
    {
        $admin = $this->user('admin', false);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('password.change.update'), [
                'current_password' => 'password123',
                'password' => 'password456',
                'password_confirmation' => 'password456',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Hash::check('password456', $admin->fresh()->password));
        $this->assertNotSame('admin-token', $admin->fresh()->current_session_token);
    }

    public function test_password_sementara_dapat_diganti_tanpa_password_lama(): void
    {
        $participant = $this->user('peserta', true);

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token])
            ->post(route('password.change.update'), [
                'password' => 'password456',
                'password_confirmation' => 'password456',
            ])
            ->assertRedirect(route('peserta.dashboard'));

        $this->assertFalse($participant->fresh()->must_change_password);
    }

    public function test_perubahan_sukarela_menolak_password_lama_yang_salah(): void
    {
        $admin = $this->user('admin', false);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('password.change.update'), [
                'current_password' => 'salah-password',
                'password' => 'password456',
                'password_confirmation' => 'password456',
            ])
            ->assertSessionHasErrors('current_password');
    }

    private function user(string $role, bool $mustChangePassword): User
    {
        return User::create([
            'nama' => ucfirst($role),
            'username' => $role,
            'password' => Hash::make('password123'),
            'role' => $role,
            'must_change_password' => $mustChangePassword,
            'current_session_token' => $role.'-token',
        ]);
    }
}
