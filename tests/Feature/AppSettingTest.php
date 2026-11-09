<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_mengubah_identitas_dan_provider_ai(): void
    {
        $admin = User::create([
            'nama' => 'Admin Pengaturan',
            'username' => 'admin_settings',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-settings-token',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-settings-token'])
            ->put(route('admin.settings.update'), [
                'site_name' => 'Kuis Publik',
                'site_tagline' => 'Belajar dan evaluasi bersama',
                'organization_name' => 'Organisasi Uji',
                'primary_color' => '#166534',
                'login_title' => 'Masuk ke kelas',
                'login_description' => 'Gunakan akun yang telah diberikan.',
                'login_layout' => 'centered',
                'ai_base_url' => 'https://ai.example.test/v1',
                'ai_api_key' => 'secret-ai-key',
                'ai_default_model' => 'provider/model',
                'ai_timeout' => 90,
                'class_cover_mode' => 'local',
                'image_base_url' => null,
                'image_api_key' => null,
                'image_model' => null,
            ]);

        $response->assertRedirect();
        $this->assertSame('Kuis Publik', AppSetting::query()->firstOrFail()->site_name);
        $this->assertSame('centered', AppSetting::query()->firstOrFail()->login_layout);
        $this->assertSame('secret-ai-key', AppSetting::query()->firstOrFail()->ai_api_key);
        $this->assertNotSame('secret-ai-key', DB::table('app_settings')->value('ai_api_key'));
    }
}
