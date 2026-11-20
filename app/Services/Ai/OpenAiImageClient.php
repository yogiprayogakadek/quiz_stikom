<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenAiImageClient implements ImageClient
{
    public function __construct(private readonly AiConfig $config)
    {
    }

    public function generate(string $prompt, string $model, string $size): string
    {
        $baseUrl = rtrim((string) $this->config->image['base_url'], '/');

        try {
            $response = Http::withToken($this->config->image['api_key'] ?? '')
                ->timeout(max(1, $this->config->image['timeout']))
                ->post($baseUrl.'/images/generations', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'size' => $size,
                    'n' => 1,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Gagal menghubungi Image API: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new RuntimeException('Image API merespons status '.$response->status());
        }

        $base64 = $response->json('data.0.b64_json');
        $url = $response->json('data.0.url');
        $bytes = is_string($base64) && $base64 !== ''
            ? base64_decode($base64, true)
            : (is_string($url) && $url !== '' ? Http::timeout(90)->get($url)->throw()->body() : false);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Image API tidak mengembalikan gambar.');
        }

        return $bytes;
    }
}
