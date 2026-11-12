<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_tampil_di_halaman_masuk(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Dibuat oleh Yogi Prayoga');
    }

    public function test_footer_tampil_di_dashboard_admin_dan_peserta(): void
    {
        $admin = User::create([
            'nama' => 'Admin Footer',
            'username' => 'admin-footer',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'token-admin-footer',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Footer',
            'username' => 'peserta-footer',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta-footer',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin-footer'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dibuat oleh Yogi Prayoga');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta-footer'])
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Dibuat oleh Yogi Prayoga');
    }
}
