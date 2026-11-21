<?php

namespace App\Services\OpenCode;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenCodeClient
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private string $agent;

    private string $model;

    /** @var array<int, string> */
    private array $fallbackModels;

    private string $directory;

    private int $timeout;

    private int $pollInterval;

    private int $sessionTimeout;

    private int $maxAttempts;

    public function __construct()
    {
        $config = config('services.opencode', []);

        $this->baseUrl = rtrim((string) ($config['url'] ?? $config['base_url'] ?? 'http://192.168.0.247:4096'), '/');
        $this->username = (string) ($config['username'] ?? 'opencode');
        $this->password = (string) ($config['password'] ?? '');
        $this->agent = (string) ($config['agent'] ?? 'data-processor');
        $this->model = (string) ($config['model'] ?? 'deepseek-v4.1-flash-free');
        $this->fallbackModels = array_values(array_filter((array) ($config['model_fallbacks'] ?? [])));
        $this->directory = (string) ($config['directory'] ?? '/srv/opencode-web');
        $this->timeout = max(5, (int) ($config['timeout'] ?? 30));
        $this->pollInterval = max(1, (int) ($config['poll_interval'] ?? 2));
        $this->sessionTimeout = max(30, (int) ($config['session_timeout'] ?? 120));
        $this->maxAttempts = max(1, (int) ($config['max_attempts'] ?? 3));

        if ($this->password === '') {
            throw new OpenCodeException('OPENCODE_PASSWORD is not configured.');
        }
    }

    public function health(): bool
    {
        try {
            $this->request('get', '/api/info');

            return true;
        } catch (OpenCodeException) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function freeModels(): array
    {
        return Cache::remember('opencode:free-models', now()->addMinutes(10), function (): array {
            $payload = $this->request('get', '/api/model');
            $models = $payload['data'] ?? [];

            if (! is_array($models)) {
                return [];
            }

            return array_values(array_filter($models, function ($model): bool {
                if (! is_array($model)) {
                    return false;
                }

                if (($model['providerID'] ?? null) !== 'opencode') {
                    return false;
                }

                if (($model['status'] ?? 'active') !== 'active') {
                    return false;
                }

                $costs = $model['cost'] ?? [];

                if (! is_array($costs) || $costs === []) {
                    return false;
                }

                foreach ($costs as $cost) {
                    if ((float) ($cost['input'] ?? 0) > 0 || (float) ($cost['output'] ?? 0) > 0) {
                        return false;
                    }
                }

                return true;
            }));
        });
    }

    /**
     * Process one payload through a fresh session, retrying with fallback models.
     *
     * @return array{session_id: string, model: string, text: string, data: array<string, mixed>|null, json_valid: bool}
     */
    public function process(string $text, ?string $model = null, ?string $agent = null): array
    {
        $models = array_values(array_unique(array_filter(array_merge(
            [$model ?: $this->model],
            $this->fallbackModels,
        ))));

        if ($models === []) {
            $models = ['deepseek-v4.1-flash-free'];
        }

        $last = null;

        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            $currentModel = $models[$attempt % count($models)];

            try {
                return $this->runSession($text, $currentModel, $agent ?: $this->agent);
            } catch (OpenCodeException $exception) {
                $last = $exception;

                if (! $exception->isRetryable() || $attempt === $this->maxAttempts - 1) {
                    throw $exception;
                }

                $seconds = min(8, 2 ** $attempt) + random_int(0, 500) / 1000;
                usleep((int) round($seconds * 1_000_000));
            }
        }

        throw $last ?? new OpenCodeException('OpenCode processing failed.');
    }

    /**
     * @return array{session_id: string, model: string, text: string, data: array<string, mixed>|null, json_valid: bool}
     */
    private function runSession(string $text, string $model, string $agent): array
    {
        $created = $this->request('post', '/api/session', [
            'directory' => $this->directory,
            'agent' => $agent,
            'model' => [
                'providerID' => 'opencode',
                'id' => $model,
            ],
        ]);

        $sessionId = (string) ($created['data']['id'] ?? '');

        if ($sessionId === '') {
            throw new OpenCodeException('OpenCode did not return a session id.', retryable: true);
        }

        try {
            $this->request('post', "/api/session/{$sessionId}/prompt", ['text' => $text]);

            $this->waitFor($sessionId);
            $result = $this->readResult($sessionId);

            if ($result['error'] !== null) {
                throw new OpenCodeException(
                    'OpenCode session failed: '.$result['error'],
                    retryable: $result['retryable'],
                );
            }

            if ($result['text'] === '') {
                throw new OpenCodeException('OpenCode returned an empty response.', retryable: true);
            }

            $data = $this->decodeJson($result['text']);

            return [
                'session_id' => $sessionId,
                'model' => $model,
                'text' => $result['text'],
                'data' => $data,
                'json_valid' => $data !== null,
            ];
        } finally {
            try {
                $this->request('delete', "/api/session/{$sessionId}");
            } catch (OpenCodeException) {
                // The result is already captured. A failed cleanup must not hide it.
            }
        }
    }

    private function waitFor(string $sessionId): void
    {
        $deadline = microtime(true) + $this->sessionTimeout;

        do {
            $payload = $this->request('get', "/api/session/{$sessionId}");
            $state = $payload['data'] ?? [];
            $outcome = $state['outcome'] ?? null;
            $idle = isset($state['time']['idle']);

            if (in_array($outcome, ['succeeded', 'failed'], true) || $idle) {
                return;
            }

            usleep($this->pollInterval * 1_000_000);
        } while (microtime(true) < $deadline);

        throw new OpenCodeException('Timed out waiting for the OpenCode session.', retryable: true);
    }

    /**
     * @return array{text: string, error: string|null, retryable: bool}
     */
    private function readResult(string $sessionId): array
    {
        $payload = $this->request('get', "/api/session/{$sessionId}/message");
        $messages = $payload['data'] ?? [];
        $assistants = [];
        $error = null;

        if (is_array($messages)) {
            foreach ($messages as $message) {
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

        $errorMessage = null;
        $retryable = false;

        if (is_array($error)) {
            $errorMessage = (string) ($error['message'] ?? $error['type'] ?? 'unknown error');
            $status = (int) ($error['status'] ?? 0);
            $type = (string) ($error['type'] ?? '');

            $retryable = $status === 429
                || $status >= 500
                || in_array($type, ['provider.free-limit', 'provider.rate-limit'], true);
        }

        return [
            'text' => trim($text),
            'error' => $errorMessage,
            'retryable' => $retryable,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $text): ?array
    {
        $candidates = [trim($text)];

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $candidates[] = trim($matches[1]);
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $candidates[] = $matches[0];
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            $request = Http::baseUrl($this->baseUrl)
                ->withBasicAuth($this->username, $this->password)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->connectTimeout(min(10, $this->timeout));

            $response = match ($method) {
                'get' => $request->get($path),
                'post' => $request->post($path, $body ?? []),
                'delete' => $request->delete($path),
                default => throw new OpenCodeException('Unsupported OpenCode request method: '.$method),
            };
        } catch (ConnectionException $exception) {
            throw new OpenCodeException(
                'Cannot reach OpenCode at '.$this->baseUrl.': '.$exception->getMessage(),
                retryable: true,
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        if ($response->status() === 204) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function exceptionFromResponse(Response $response): OpenCodeException
    {
        $status = $response->status();
        $body = $response->json();
        $message = '';

        if (is_array($body)) {
            $message = (string) ($body['message'] ?? $body['error']['message'] ?? $body['data']['message'] ?? '');
        }

        if ($message === '') {
            $message = trim($response->body());
        }

        if ($message === '') {
            $message = 'HTTP request failed with status '.$status;
        }

        $retryable = $status === 429
            || $status >= 500
            || (is_array($body) && in_array($body['reason'] ?? null, ['rate_limit', 'free-limit'], true));

        return new OpenCodeException($message, $status, $retryable);
    }
}
