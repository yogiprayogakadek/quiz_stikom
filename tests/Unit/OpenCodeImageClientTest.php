<?php

namespace Tests\Unit;

use App\Services\Ai\AiConfig;
use App\Services\Ai\OpenCodeImageClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenCodeImageClientTest extends TestCase
{
    private function config(array $image = []): AiConfig
    {
        return new AiConfig('opencode',
            ['base_url' => '', 'api_key' => null, 'model' => '', 'timeout' => 60],
            ['base_url' => 'https://oc.example.test/', 'username' => 'opencode', 'password' => 'rahasia', 'model' => 'chat-model', 'timeout' => 120],
            array_merge([
                'base_url' => 'https://oc.example.test/',
                'api_key' => null,
                'model' => 'grok-imagine-image-2.0',
                'timeout' => 150,
            ], $image));
    }

    public function test_generate_memakai_aspect_dan_men_decode_base64(): void
    {
        Http::fake(['https://oc.example.test/api/experimental/generate/image' => Http::response([
            'data' => ['images' => [['type' => 'image', 'mime' => 'image/png', 'data' => base64_encode('gambar-biner')]]],
        ])]);

        $bytes = (new OpenCodeImageClient($this->config()))->generate('prompt', 'grok-imagine-image-2.0', '1536x1024');

        $this->assertSame('gambar-biner', $bytes);
        Http::assertSent(fn ($r) => $r['model'] === ['id' => 'grok-imagine-image-2.0', 'providerID' => 'opencode']
            && $r['aspect'] === '16:9' && $r['n'] === 1 && $r['resolution'] === '2K');
    }

    public function test_gambar_kosong_menghasilkan_pesan_jelas(): void
    {
        Http::fake(['*' => Http::response(['data' => ['images' => []]])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak mengembalikan gambar');

        (new OpenCodeImageClient($this->config()))->generate('prompt', 'grok-imagine-image-2.0', '1536x1024');
    }

    public function test_status_gagal_menyebut_status(): void
    {
        Http::fake(['*' => Http::response(['_tag' => 'ProviderRequestError'], 502)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('502');

        (new OpenCodeImageClient($this->config()))->generate('prompt', 'grok-imagine-image-2.0', '1536x1024');
    }
}
