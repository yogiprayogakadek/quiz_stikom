<?php

namespace App\Services\Ai;

use App\Exceptions\AiRequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OpenAiTextClient implements TextClient
{
    public function __construct(private readonly AiConfig $config)
    {
    }

    public function models(int $timeout): array
    {
        try {
            $response = Http::withToken($this->config->openai['api_key'] ?? '')
                ->timeout(max(1, $timeout))
                ->get($this->baseUrl().'/models');
        } catch (Throwable $e) {
            throw new AiRequestException('Tidak bisa menghubungi endpoint. Periksa Base URL dan koneksi server.');
        }

        if (! $response->successful()) {
            throw new AiRequestException($this->modelsError($response->status()), $response->status());
        }

        return $this->parseModels($response->json());
    }

    public function chat(array $messages, ?string $model, float $temperature): string
    {
        $model = $model ?: $this->config->defaultModel();

        try {
            $response = Http::withToken($this->config->openai['api_key'] ?? '')
                ->timeout(max(1, $this->config->openai['timeout']))
                ->post($this->baseUrl().'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'stream' => false,
                ]);
        } catch (Throwable $e) {
            throw new AiRequestException("Gagal menghubungi layanan AI: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new AiRequestException($this->chatError($response, $model), $response->status(), $this->retryAfter($response));
        }

        return $this->extractRawContent($response);
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->config->openai['base_url'], '/');
    }

    private function retryAfter(Response $response): ?int
    {
        $retryAfter = (int) $response->header('Retry-After');

        return $retryAfter > 0 ? $retryAfter : null;
    }

    private function chatError(Response $response, ?string $model = null): string
    {
        $status = $response->status();
        $alasan = $this->alasanProvider($response);
        $model = $model ?: $this->config->defaultModel();
        $retryAfter = (int) $response->header('Retry-After');
        $detail = $alasan === '' ? '' : ': '.$alasan;

        return match (true) {
            in_array($status, [401, 403], true) => "API key ditolak (status {$status}){$detail}. Periksa kembali API key.",
            $status === 404 => "Model {$model} tidak ditemukan (404){$detail}. Pilih model lain di daftar.",
            $status === 429 => "Layanan AI membatasi permintaan (429){$detail}. "
                .($retryAfter > 0 ? "Coba lagi dalam {$retryAfter} detik" : 'Coba lagi beberapa saat')
                .' atau ganti model default di Pengaturan > AI.',
            $status >= 500 => "Layanan AI bermasalah (status {$status}){$detail}. Coba lagi beberapa saat.",
            default => "Layanan AI menolak permintaan (status {$status}){$detail}.",
        };
    }

    private function modelsError(int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => "API key ditolak (status {$status}). Periksa kembali API key.",
            $status === 404 => 'Endpoint /models tidak ditemukan (404). Periksa Base URL.',
            $status === 429 => 'Layanan AI membatasi permintaan (429). Coba lagi nanti.',
            $status >= 500 => "Layanan AI bermasalah (status {$status}).",
            default => "Endpoint merespons dengan status {$status}.",
        };
    }

    /**
     * Mengambil alasan singkat dari body error provider.
     */
    private function alasanProvider(Response $response): string
    {
        $json = $response->json();
        $alasan = '';

        if (is_array($json)) {
            $error = $json['error'] ?? null;
            $kandidat = [
                is_array($error) ? ($error['message'] ?? null) : $error,
                $json['message'] ?? null,
                $json['detail'] ?? null,
            ];

            foreach ($kandidat as $nilai) {
                if (is_string($nilai) && trim($nilai) !== '') {
                    $alasan = $nilai;
                    break;
                }
            }
        }

        if ($alasan === '') {
            $alasan = strip_tags($response->body());
        }

        $alasan = Str::limit(trim((string) preg_replace('/\s+/', ' ', $alasan)), 200, '...');

        if (! str_ends_with($alasan, '...')) {
            $alasan = rtrim($alasan, '. ');
        }

        return $alasan;
    }

    /**
     * Mengelompokkan daftar model dari respons /models per provider.
     *
     * @return array<string, array<string>>
     */
    private function parseModels(mixed $json): array
    {
        $items = is_array($json) && isset($json['data']) && is_array($json['data'])
            ? $json['data']
            : (is_array($json) ? $json : []);

        $grouped = [];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : (is_string($item) ? $item : null);
            if (! $id) {
                continue;
            }

            $ownedBy = is_array($item) ? ($item['owned_by'] ?? '') : '';
            $provider = $this->detectProvider((string) $id, (string) $ownedBy);

            $grouped[$provider][] = (string) $id;
        }

        foreach ($grouped as $provider => &$models) {
            sort($models);
        }
        unset($models);

        uksort($grouped, function ($a, $b) {
            $priority = ['Google' => 1, 'OpenAI' => 2, 'Anthropic' => 3, 'DeepSeek' => 4, 'Meta' => 5];
            $pA = $priority[$a] ?? 99;
            $pB = $priority[$b] ?? 99;
            if ($pA === $pB) {
                return strcmp($a, $b);
            }

            return $pA <=> $pB;
        });

        return $grouped;
    }

    /**
     * Mengekstrak konten teks dari respons API secara defensif (mendukung standard, array part, atau SSE chunk).
     */
    private function extractRawContent(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $content = $json['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') {
                return trim($content);
            }

            if (is_array($content)) {
                $pieces = [];
                foreach ($content as $part) {
                    if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                        $pieces[] = $part['text'];
                    } elseif (is_string($part)) {
                        $pieces[] = $part;
                    }
                }
                $combined = trim(implode('', $pieces));
                if ($combined !== '') {
                    return $combined;
                }
            }

            $text = $json['choices'][0]['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }

            $reasoning = $json['choices'][0]['message']['reasoning_content'] ?? null;
            if (is_string($reasoning) && trim($reasoning) !== '') {
                return trim($reasoning);
            }

            $refusal = $json['choices'][0]['message']['refusal'] ?? null;
            if (is_string($refusal) && trim($refusal) !== '') {
                throw new RuntimeException("Layanan AI menolak permintaan: {$refusal}");
            }
        }

        $body = $response->body();
        if (str_contains($body, 'data:')) {
            $accumulated = '';
            foreach (explode("\n", $body) as $line) {
                $line = trim($line);
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }
                $chunk = json_decode($payload, true);
                if (is_array($chunk)) {
                    $delta = $chunk['choices'][0]['delta']['content'] ?? ($chunk['choices'][0]['text'] ?? '');
                    if (is_string($delta)) {
                        $accumulated .= $delta;
                    }
                }
            }

            if (trim($accumulated) !== '') {
                return trim($accumulated);
            }
        }

        $preview = Str::limit(trim($body), 150);
        throw new RuntimeException("Layanan AI mengembalikan konten kosong. (Respons: {$preview})");
    }

    /**
     * Deteksi nama provider dari ID model atau atribut owned_by.
     */
    private function detectProvider(string $id, string $ownedBy): string
    {
        $idLower = Str::lower($id);
        $ownedLower = Str::lower($ownedBy);

        if (Str::contains($idLower, ['gemini', 'google', 'ag/gemini']) || $ownedLower === 'google') {
            return 'Google';
        }
        if (Str::contains($idLower, ['gpt', 'openai', 'o1', 'o3', 'ag/gpt']) || $ownedLower === 'openai') {
            return 'OpenAI';
        }
        if (Str::contains($idLower, ['claude', 'anthropic', 'ag/claude']) || $ownedLower === 'anthropic') {
            return 'Anthropic';
        }
        if (Str::contains($idLower, ['deepseek']) || $ownedLower === 'deepseek') {
            return 'DeepSeek';
        }
        if (Str::contains($idLower, ['llama', 'meta']) || $ownedLower === 'meta') {
            return 'Meta';
        }
        if (Str::contains($idLower, ['qwen', 'alibaba'])) {
            return 'Qwen';
        }
        if (Str::contains($idLower, ['mistral'])) {
            return 'Mistral';
        }

        return $ownedBy !== '' ? ucfirst($ownedBy) : 'Lainnya';
    }
}
