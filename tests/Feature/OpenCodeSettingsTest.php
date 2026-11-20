<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenCodeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_platform_memakai_config_env(): void
    {
        config(['services.opencode.username' => 'opencode', 'services.opencode.timeout' => 120]);

        $settings = AppSetting::current();

        $this->assertSame('openai', $settings->ai_provider);
        $this->assertSame('opencode', $settings->opencode_username);
        $this->assertSame(120, $settings->opencode_timeout);
        $this->assertContains('opencode_password', AppSetting::fields());
    }

    public function test_workspace_mewarisi_provider_dan_field_opencode_dari_platform(): void
    {
        AppSetting::query()->create([
            'id' => 1,
            'ai_provider' => 'opencode',
            'opencode_base_url' => 'https://oc.example.test',
            'opencode_model' => 'model-dari-platform',
        ]);
        $organization = Organization::create([
            'name' => 'Kampus Warisan', 'slug' => 'kampus-warisan', 'type' => 'education',
            'timezone' => 'Asia/Jakarta', 'is_active' => true,
        ]);

        $effective = OrganizationSetting::effective($organization->id);

        $this->assertSame('opencode', $effective->ai_provider);
        $this->assertSame('https://oc.example.test', $effective->opencode_base_url);
        $this->assertSame('model-dari-platform', $effective->opencode_model);
    }
}
