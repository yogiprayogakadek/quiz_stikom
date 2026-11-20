<?php

namespace Tests\Unit;

use App\Services\Ai\AiConfig;
use App\Services\Ai\AiManager;
use App\Services\Ai\OpenAiImageClient;
use App\Services\Ai\OpenAiTextClient;
use App\Services\Ai\OpenCodeImageClient;
use App\Services\Ai\OpenCodeTextClient;
use Tests\TestCase;

class AiManagerTest extends TestCase
{
    private function config(string $provider, array $openai = [], array $opencode = [], array $image = []): AiConfig
    {
        return new AiConfig($provider,
            array_merge(['base_url' => 'https://a.test/v1', 'api_key' => 'k', 'model' => 'm', 'timeout' => 10], $openai),
            array_merge(['base_url' => 'https://oc.test', 'username' => 'opencode', 'password' => 'p', 'model' => 'oc-model', 'timeout' => 120], $opencode),
            array_merge(['base_url' => 'https://a.test/v1', 'api_key' => 'k', 'model' => 'img', 'timeout' => 150], $image));
    }

    public function test_text_mengikuti_provider_aktif(): void
    {
        $this->assertInstanceOf(OpenCodeTextClient::class, (new AiManager($this->config('opencode')))->text());
        $this->assertInstanceOf(OpenAiTextClient::class, (new AiManager($this->config('openai')))->text());
    }

    public function test_image_mengikuti_provider_aktif(): void
    {
        $this->assertInstanceOf(OpenCodeImageClient::class, (new AiManager($this->config('opencode')))->image());
        $this->assertInstanceOf(OpenAiImageClient::class, (new AiManager($this->config('openai')))->image());
    }

    public function test_image_null_saat_konfigurasi_belum_lengkap(): void
    {
        $manager = new AiManager($this->config('opencode',
            opencode: ['password' => null],
            image: ['model' => '']));

        $this->assertNull($manager->image());
    }

    public function test_with_timeout_hanya_mengubah_timeout(): void
    {
        $config = $this->config('opencode')->withTimeout(12);

        $this->assertSame(12, $config->opencode['timeout']);
        $this->assertSame(12, $config->openai['timeout']);
        $this->assertSame('oc-model', $config->opencode['model']);
    }
}
