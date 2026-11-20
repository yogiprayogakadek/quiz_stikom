<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenCodeImageClient implements ImageClient
{
    public function __construct(private readonly AiConfig $config)
    {
    }

    public function generate(string $prompt, string $model, string $size): string
    {
        $baseUrl = rtrim((string) $this->config->image['base_url'], '/');

        try {
            $response = Http::withBasicAuth((string) $this->config->opencode['username'], (string) $this->config->opencode['password'])
                ->acceptJson()
                ->timeout(max(1, $this->config->image['timeout']))
                ->post($baseUrl.'/api/experimental/generate/image', [
                    'model' => ['id' => $model, 'providerID' => 'opencode'],
                    'prompt' => $prompt,
                    'n' => 1,
                    'aspect' => '16:9',
                    'resolution' => '2K',
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Gagal menghubungi server OpenCode: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new RuntimeException('Server OpenCode merespons status '.$response->status());
        }

        $base64 = $response->json('data.images.0.data');
        $bytes = is_string($base64) && $base64 !== '' ? base64_decode($base64, true) : false;

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Server OpenCode tidak mengembalikan gambar.');
        }

        return $bytes;
    }
}
