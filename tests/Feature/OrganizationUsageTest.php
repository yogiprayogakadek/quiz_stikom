<?php

namespace Tests\Feature;

use App\Jobs\GenerateClassCoverJob;
use App\Models\AiUsageEvent;
use App\Models\AppSetting;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Services\OrganizationUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pemakaian_ai_menjumlahkan_request_bulan_berjalan(): void
    {
        $organization = Organization::create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);

        AiUsageEvent::create([
            'organization_id' => $organization->id,
            'kind' => 'questions',
            'requests' => 2,
        ]);

        $old = AiUsageEvent::create([
            'organization_id' => $organization->id,
            'kind' => 'essay',
            'requests' => 3,
        ]);
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $this->assertSame(2, app(OrganizationUsage::class)->aiUsed($organization->id));
    }

    public function test_pemakaian_penyimpanan_menghitung_logo_dan_cover(): void
    {
        Storage::fake('public');

        $organization = Organization::create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'logo_path' => 'branding/logo.png',
        ]);

        Kelas::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Alpha',
            'pin' => 'ALPHA1',
            'cover_path' => 'class-covers/cover.png',
        ]);

        Storage::disk('public')->put('branding/logo.png', str_repeat('a', 100));
        Storage::disk('public')->put('class-covers/cover.png', str_repeat('b', 200));

        $this->assertSame(300, app(OrganizationUsage::class)->storageUsed($organization->id));
    }

    public function test_cover_ai_dibuang_saat_kuota_penyimpanan_habis(): void
    {
        Storage::fake('public');
        config(['platform.ai_monthly_requests' => 100, 'platform.storage_mb' => 0]);
        AppSetting::create([
            'class_cover_mode' => 'ai',
            'image_base_url' => 'https://image.example.test/v1',
            'image_api_key' => 'image-key',
            'image_model' => 'image-model',
        ]);

        $organization = Organization::create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        $kelas = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Alpha',
            'pin' => 'ALPHA1',
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=');
        Http::fake([
            '*/images/generations' => Http::response(['data' => [['b64_json' => base64_encode($png)]]]),
        ]);

        (new GenerateClassCoverJob($kelas->id))->handle(app(OrganizationUsage::class));

        $this->assertNull($kelas->fresh()->cover_path);
        $this->assertDatabaseHas('ai_usage_events', [
            'organization_id' => $organization->id,
            'kind' => 'cover',
            'requests' => 1,
        ]);
    }
}
