<?php

namespace Tests\Unit;

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
}
