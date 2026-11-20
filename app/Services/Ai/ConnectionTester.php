<?php

namespace App\Services\Ai;

use App\Exceptions\AiRequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConnectionTester
{
    public function __construct(private readonly AiManager $ai)
    {
    }

    /**
     * Menguji endpoint AI, daftar model, dan apakah model default benar-benar bisa menjawab.
     * Tidak memakai cache maupun fallback agar hasil tes jujur.
     *
     * @return array{
     *     ok: bool,
     *     endpoint: array{ok: bool, status: int|null, pesan: string},
     *     probe: array{ok: bool, status: int|null, pesan: string}|null,
     *     model: array<string, array<string>>
     * }
     */
    public function test(?string $model = null, int $timeout = 25): array
    {
        $config = $this->ai->config();
        $baseUrl = rtrim($config->textBaseUrl(), '/');
        $model = trim((string) ($model ?: $config->defaultModel()));
        // ponytail: total tes dibatasi 25 detik agar tidak kena batas eksekusi PHP.
        $timeout = min(max($timeout, 5), 30);
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;

        $modelTimeout = min($timeout, 10);

        try {
            $models = (new AiManager($config->withTimeout($modelTimeout)))->text()->models($modelTimeout);
        } catch (AiRequestException $e) {
            Log::warning('Tes koneksi AI gagal: '.$e->getMessage(), ['base_url' => $baseUrl]);

            return [
                'ok' => false,
                'endpoint' => [
                    'ok' => false,
                    'status' => $e->status,
                    'pesan' => $e->getMessage(),
                ],
                'probe' => null,
                'model' => [],
            ];
        } catch (Throwable $e) {
            Log::warning('Tes koneksi AI gagal: '.$e->getMessage(), ['base_url' => $baseUrl]);

            return [
                'ok' => false,
                'endpoint' => [
                    'ok' => false,
                    'status' => null,
                    'pesan' => 'Tidak bisa menghubungi endpoint. Periksa Base URL dan koneksi server.',
                ],
                'probe' => null,
                'model' => [],
            ];
        }

        $jumlah = array_sum(array_map('count', $models));
        $probe = $this->probe($config, $model, min($timeout, 15));

        return [
            'ok' => $probe['ok'],
            'endpoint' => [
                'ok' => true,
                'status' => 200,
                'pesan' => $models === []
                    ? "Endpoint {$host} merespons, tetapi tidak ada model yang dikembalikan."
                    : "Terhubung ke {$host}. {$jumlah} model ditemukan.",
            ],
            'probe' => $probe,
            'model' => $models,
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, pesan: string}
     */
    private function probe(AiConfig $config, string $model, int $timeout): array
    {
        if ($model === '') {
            return [
                'ok' => false,
                'status' => null,
                'pesan' => 'Model default belum diisi.',
            ];
        }

        try {
            (new AiManager($config->withTimeout($timeout)))->text()->chat([
                ['role' => 'user', 'content' => 'Balas hanya: ok'],
            ], $model, 0.0);
        } catch (AiRequestException $e) {
            return [
                'ok' => false,
                'status' => $e->status,
                'pesan' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::warning('Probe model AI gagal: '.$e->getMessage(), ['model' => $model]);

            return [
                'ok' => false,
                'status' => null,
                'pesan' => 'Tidak bisa menghubungi endpoint saat menguji model default.',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'pesan' => "Model default {$model} merespons.",
        ];
    }
}
