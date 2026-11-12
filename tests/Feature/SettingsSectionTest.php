<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_simpan_provider_ai_tidak_mengubah_identitas_workspace(): void
    {
        $organization = Organization::create([
            'name' => 'Kampus Pengaturan',
            'slug' => 'kampus-pengaturan',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $owner = User::create([
            'nama' => 'Owner Pengaturan',
            'username' => 'owner_pengaturan',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'owner-pengaturan-token',
        ]);

        $owner->organizations()->syncWithoutDetaching([$organization->id => [
            'role' => 'owner',
            'status' => 'active',
        ]]);

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'site_name' => 'Identitas Awal',
            'ai_default_model' => 'model-lama',
            'ai_timeout' => 30,
            'locale' => 'id',
        ]);

        $this->actingAs($owner)
            ->withSession([
                'auth_token' => $owner->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                '_section' => 'ai',
                'ai_base_url' => 'https://ai.example.test/v1',
                'ai_default_model' => 'model-baru',
                'ai_timeout' => 45,
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('settings_tab', 'ai');

        $settings = OrganizationSetting::where('organization_id', $organization->id)->firstOrFail();

        $this->assertSame('Identitas Awal', $settings->site_name);
        $this->assertSame('model-baru', $settings->ai_default_model);
        $this->assertSame(45, $settings->ai_timeout);
    }

    public function test_halaman_pengaturan_menampilkan_tab_dan_tab_aktif_dari_query(): void
    {
        $organization = Organization::create([
            'name' => 'Kampus Tab',
            'slug' => 'kampus-tab',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $owner = User::create([
            'nama' => 'Owner Tab',
            'username' => 'owner_tab',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'owner-tab-token',
        ]);

        $owner->organizations()->syncWithoutDetaching([$organization->id => [
            'role' => 'owner',
            'status' => 'active',
        ]]);

        $this->actingAs($owner)
            ->withSession([
                'auth_token' => $owner->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->get(route('admin.settings.edit', ['tab' => 'ai']))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('panel-ai', false)
            ->assertViewHas('activeTab', 'ai');
    }
}
