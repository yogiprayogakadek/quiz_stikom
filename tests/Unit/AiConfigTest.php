<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Services\Ai\AiConfig;
use Tests\TestCase;

class AiConfigTest extends TestCase
{
    public function test_ai_config_has_required_keys(): void
    {
        $config = config('services.ai');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('base_url', $config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('default_model', $config);
        $this->assertArrayHasKey('timeout', $config);
    }

    public function test_opencode_config_has_required_keys(): void
    {
        $config = config('services.opencode');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('base_url', $config);
        $this->assertArrayHasKey('username', $config);
        $this->assertArrayHasKey('password', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertSame(120, $config['timeout']);
    }

    public function test_ai_config_menimpa_nilai_dari_settings_dan_memakai_default_provider_openai(): void
    {
        config([
            'services.ai.base_url' => 'https://env.example.test/v1',
            'services.ai.default_model' => 'env-model',
            'services.opencode.username' => 'opencode',
        ]);

        $settings = new AppSetting([
            'opencode_base_url' => 'https://oc.example.test/',
            'opencode_password' => 'rahasia',
            'opencode_timeout' => 90,
        ]);

        $config = AiConfig::fromSettings($settings);

        $this->assertSame('openai', $config->provider);
        $this->assertSame('https://env.example.test/v1', $config->openai['base_url']);
        $this->assertSame('https://oc.example.test/', $config->opencode['base_url']);
        $this->assertSame('opencode', $config->opencode['username']);
        $this->assertSame('rahasia', $config->opencode['password']);
        $this->assertSame(90, $config->opencode['timeout']);
        $this->assertSame('https://env.example.test/v1', $config->textBaseUrl());
    }

    public function test_override_kosong_tidak_menimpa_nilai_tersimpan(): void
    {
        $settings = new AppSetting([
            'ai_provider' => 'opencode',
            'opencode_base_url' => 'https://saved.example.test',
            'opencode_password' => 'saved-secret',
            'opencode_model' => 'saved-model',
        ]);

        $config = AiConfig::fromSettings($settings, [
            'opencode_base_url' => '',
            'opencode_password' => '',
            'opencode_model' => 'form-model',
        ]);

        $this->assertSame('opencode', $config->provider);
        $this->assertSame('https://saved.example.test', $config->opencode['base_url']);
        $this->assertSame('saved-secret', $config->opencode['password']);
        $this->assertSame('form-model', $config->opencode['model']);
        $this->assertSame('https://saved.example.test', $config->textBaseUrl());
        $this->assertSame('form-model', $config->defaultModel());
    }
}
