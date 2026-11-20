<?php

namespace App\Services\Ai;

use App\Exceptions\AiRequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OpenCodeTextClient implements TextClient
{
    public function __construct(private readonly AiConfig $config)
    {
    }

    public function models(int $timeout = 15): array
    {
        return ['OpenCode' => $this->modelsOfType('chat', $timeout)];
    }

    /**
     * @return array<int, string>
     */
    public function modelsOfType(string $type, int $timeout = 15): array
    {
        $items = $this->modelItems([], $timeout);

        if ($items === []) {
            $items = $this->modelItems(['types' => $type], $timeout);
        }

        $ids = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }
            if (($item['type'] ?? $type) !== $type) {
                continue;
            }
            $ids[] = (string) $item['id'];
        }

        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * Server OpenCode baru mengabaikan filter `types` (mengembalikan kosong),
     * jadi daftar diambil tanpa filter lalu disaring di sisi klien.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function modelItems(array $query, int $timeout): array
    {
        $items = $this->send('get', '/api/model', $query, $timeout)->json('data');

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    public function chat(array $messages, ?string $model, float $temperature): string
    {
        $model = trim((string) ($model ?: $this->config->defaultModel()));

        if ($model === '') {
            throw new AiRequestException('Model OpenCode belum dipilih. Pilih model di daftar Pengaturan > Provider AI.');
        }

        $prompt = implode("\n\n", array_map(
            fn (array $message) => (string) ($message['content'] ?? ''),
            $messages,
        ));

        $response = $this->send('post', '/api/experimental/generate/text', [
            'prompt' => $prompt,
            'model' => ['id' => $model, 'providerID' => 'opencode'],
        ], $this->config->opencode['timeout'], $model);

        $text = $response->json('data.text');

        if (! is_string($text) || trim($text) === '') {
            $preview = Str::limit(trim($response->body()), 150);
            throw new RuntimeException("Server OpenCode mengembalikan konten kosong. (Respons: {$preview})");
        }

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload, int $timeout, ?string $model = null): Response
    {
        $baseUrl = rtrim((string) $this->config->opencode['base_url'], '/');
        $password = (string) $this->config->opencode['password'];

        if ($baseUrl === '' || $password === '') {
            throw new AiRequestException('OpenCode belum dikonfigurasi. Isi Base URL dan password di Pengaturan > Provider AI.');
        }

        $pending = Http::withBasicAuth((string) $this->config->opencode['username'], $password)
            ->acceptJson()
            ->timeout(max(1, min($timeout, max(1, $this->config->opencode['timeout']))));

        try {
            $response = $method === 'get'
                ? $pending->get($baseUrl.$path, $payload)
                : $pending->post($baseUrl.$path, $payload);
        } catch (Throwable $e) {
            throw new AiRequestException("Gagal menghubungi server OpenCode: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new AiRequestException(
                $this->describe($response, $model),
                $response->status(),
                $this->retryAfter($response),
            );
        }

        return $response;
    }

    private function describe(Response $response, ?string $model): string
    {
        $status = $response->status();
        $detail = $this->detail($response);
        $detail = $detail === '' ? '' : ': '.$detail;
        $retryAfter = $this->retryAfter($response);
        $notFound = $response->json('_tag') === 'ModelNotFoundError' || $status === 404;
        $isRateLimit = $status === 429
            || (int) $response->json('status') === 429
            || $response->json('reason') === 'rate_limit';

        return match (true) {
            in_array($status, [401, 403], true) => "Username atau password OpenCode ditolak (status {$status}){$detail}. Periksa kembali pengaturan OpenCode.",
            $notFound => $model
                ? "Model {$model} tidak ditemukan di server OpenCode{$detail}."
                : "Endpoint OpenCode tidak ditemukan (404){$detail}. Periksa Base URL.",
            $isRateLimit => "Server OpenCode membatasi permintaan{$detail}. "
                .($retryAfter ? "Coba lagi dalam {$retryAfter} detik" : 'Coba lagi beberapa saat')
                .' atau pilih model lain.',
            $status >= 500 => "Server OpenCode bermasalah (status {$status}){$detail}.",
            default => "Server OpenCode menolak permintaan (status {$status}){$detail}.",
        };
    }

    private function detail(Response $response): string
    {
        $json = $response->json();
        $detail = '';

        if (is_array($json)) {
            foreach ([$json['message'] ?? null, $json['reason'] ?? null, $json['detail'] ?? null] as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $detail = $candidate;
                    break;
                }
            }
        }

        if ($detail === '') {
            return '';
        }

        return rtrim(Str::limit(trim((string) preg_replace('/\s+/', ' ', $detail)), 200, '...'), '. ');
    }

    private function retryAfter(Response $response): ?int
    {
        $milliseconds = $response->json('retryAfterMs');

        if (! is_numeric($milliseconds) || (int) $milliseconds <= 0) {
            return null;
        }

        return (int) round(((int) $milliseconds) / 1000);
    }
}
