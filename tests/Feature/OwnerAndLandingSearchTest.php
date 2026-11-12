<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerAndLandingSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_list_menampilkan_admin_tanpa_workspace_dan_mengabaikan_platform_admin(): void
    {
        $master = $this->user('master_owner_list', true);
        $ownerless = $this->user('admin_tanpa_workspace');

        Organization::create([
            'name' => 'Kampus Satu',
            'slug' => 'kampus-satu',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.owners.index'))
            ->assertOk()
            ->assertSee('Admin Tanpa Workspace')
            ->assertSee('Belum punya workspace')
            ->assertSee('Cari nama, username, email, atau workspace')
            ->assertViewHas('owners', fn ($owners) => $owners->contains('id', $ownerless->id)
                && ! $owners->contains('id', $master->id));
    }

    public function test_pencarian_landing_mengembalikan_json_organisasi_aktif(): void
    {
        Organization::create([
            'name' => 'Kampus Alpha',
            'slug' => 'kampus-alpha',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        Organization::create([
            'name' => 'Kampus Beta',
            'slug' => 'kampus-beta',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => false,
        ]);

        $this->getJson(route('organizations.search', ['q' => 'kampus']))
            ->assertOk()
            ->assertJsonCount(1, 'organizations')
            ->assertJsonPath('organizations.0.name', 'Kampus Alpha')
            ->assertJsonPath('organizations.0.slug', 'kampus-alpha');

        $this->getJson(route('organizations.search', ['q' => '']))
            ->assertOk()
            ->assertJsonCount(0, 'organizations');
    }

    private function user(string $username, bool $platform = false): User
    {
        return User::create([
            'nama' => str($username)->replace('_', ' ')->title()->toString(),
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_platform_admin' => $platform,
            'current_session_token' => $username.'-token',
        ]);
    }
}
