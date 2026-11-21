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
    /** @var array<int, array<string, mixed>>|null */
    private ?array $cachedItems = null;

    public function __construct(private readonly AiConfig $config)
    {
    }

    public function models(int $timeout = 15): array
    {
        return ['OpenCode' => $this->modelsOfType('chat', $timeout)];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function modelVariants(int $timeout = 15): array
    {
        $items = $this->modelItems([], $timeout);

        if ($items === []) {
            $items = $this->modelItems(['types' => 'chat'], $timeout);
        }

        $result = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $id = (string) $item['id'];
            $variants = [];

            foreach ((array) ($item['variants'] ?? []) as $variant) {
                if (is_array($variant) && isset($variant['id']) && is_string($variant['id'])) {
                    $variants[] = $variant['id'];
                }
            }

            if ($variants !== []) {
                $result[$id] = $variants;
            }
        }

        return $result;
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
        if ($query === [] && $this->cachedItems !== null) {
            return $this->cachedItems;
        }

        $items = $this->send('get', '/api/model', $query, $timeout)->json('data');

        if (! is_array($items)) {
            return [];
        }

        $result = array_values(array_filter($items, 'is_array'));

        if ($query === []) {
            $this->cachedItems = $result;
        }

        return $result;
    }

    private function resolveVariant(string $model, string $variant, int $timeout): ?string
    {
        try {
            $items = $this->modelItems([], $timeout);

            if ($items === []) {
                $items = $this->modelItems(['types' => 'chat'], $timeout);
            }
        } catch (Throwable) {
            return null;
        }

        foreach ($items as $item) {
            if (! is_array($item) || ($item['id'] ?? null) !== $model) {
                continue;
            }

            $variants = $item['variants'] ?? [];

            if (! is_array($variants)) {
                return null;
            }

            foreach ($variants as $v) {
                if (is_array($v) && ($v['id'] ?? null) === $variant) {
                    return $variant;
                }
            }

            return null;
        }

        return null;
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

        $timeout = max(5, (int) ($this->config->opencode['timeout'] ?? 120));
        $pollInterval = max(1, (int) config('services.opencode.poll_interval', 2));
        $directory = (string) config('services.opencode.directory', '/srv/opencode-web');
        $agent = (string) config('services.opencode.agent', 'data-processor');

        // ponytail: siklus hidup sesi langsung via HTTP tanpa wrapper terpisah
        $modelPayload = ['id' => $model, 'providerID' => 'opencode'];
        $variant = trim((string) ($this->config->opencode['variant'] ?? ''));

        if ($variant !== '') {
            $resolvedVariant = $this->resolveVariant($model, $variant, $timeout);
            if ($resolvedVariant !== null) {
                $modelPayload['variant'] = $resolvedVariant;
            }
        }

        $sessionResponse = $this->send('post', '/api/session', [
            'directory' => $directory,
            'agent' => $agent,
            'model' => $modelPayload,
        ], $timeout, $model);

        $sessionId = (string) ($sessionResponse->json('data.id') ?? '');

        if ($sessionId === '') {
            throw new AiRequestException('Server OpenCode tidak mengembalikan ID sesi.');
        }

        try {
            $this->send('post', "/api/session/{$sessionId}/prompt", [
                'text' => $prompt,
            ], $timeout, $model);

            $this->waitForSession($sessionId, $timeout, $pollInterval);

            return $this->readAssistantMessage($sessionId, $timeout);
        } finally {
            try {
                $this->send('delete', "/api/session/{$sessionId}", [], $timeout);
            } catch (Throwable) {
                // Pembersihan sesi tidak boleh menyembunyikan hasil atau exception utama.
            }
        }
    }

    private function waitForSession(string $sessionId, int $timeout, int $pollInterval): void
    {
        $deadline = microtime(true) + $timeout;

        do {
            $payload = $this->send('get', "/api/session/{$sessionId}", [], $timeout)->json('data');
            $outcome = is_array($payload) ? ($payload['outcome'] ?? null) : null;
            $idle = is_array($payload) && isset($payload['time']['idle']);

            if (in_array($outcome, ['succeeded', 'failed'], true) || $idle) {
                return;
            }

            usleep($pollInterval * 1_000_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Waktu tunggu respons sesi OpenCode habis.');
    }

    private function readAssistantMessage(string $sessionId, int $timeout): string
    {
        $payload = $this->send('get', "/api/session/{$sessionId}/message", [], $timeout)->json('data');
        $messages = is_array($payload) ? $payload : [];
        $assistants = [];
        $error = null;

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $type = $message['type'] ?? '';

            if ($type === 'assistant') {
                $assistants[] = $message;

                if (! empty($message['error'])) {
                    $error = $message['error'];
                }
            }

            if ($type === 'idle' && ! empty($message['error'])) {
                $error = $message['error'];
            }
        }

        if (is_array($error)) {
            $errorMessage = (string) ($error['message'] ?? $error['type'] ?? 'terjadi kesalahan pada sesi OpenCode');
            $status = (int) ($error['status'] ?? 0);
            throw new AiRequestException("Sesi OpenCode gagal: {$errorMessage}", $status > 0 ? $status : null);
        }

        usort($assistants, fn (array $a, array $b): int => ((int) ($a['time']['created'] ?? 0)) <=> ((int) ($b['time']['created'] ?? 0)));

        $text = '';
        foreach ($assistants as $assistant) {
            $chunk = '';
            foreach (($assistant['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $chunk .= (string) ($part['text'] ?? '');
                }
            }

            if (trim($chunk) !== '') {
                $text = $chunk;
            }
        }

        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new RuntimeException('Server OpenCode mengembalikan konten kosong.');
        }

        return $trimmed;
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
            $response = match ($method) {
                'get' => $pending->get($baseUrl.$path, $payload),
                'delete' => $pending->delete($baseUrl.$path, $payload),
                default => $pending->post($baseUrl.$path, $payload),
            };
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
