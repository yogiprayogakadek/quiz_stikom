<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiQuestionService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $defaultModel;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ai.base_url', 'https://api.9router.com/v1'), '/');
        $this->apiKey = config('services.ai.api_key');
        $this->defaultModel = (string) config('services.ai.default_model', 'ag/gemini-3.8-flash-medium');
        $this->timeout = (int) config('services.ai.timeout', 60);
    }

    /**
     * Mendapatkan daftar model AI yang tersedia dari 9router, dikelompokkan per provider.
     *
     * @return array<string, array<string>>
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        $cacheKey = '9router_models';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $response = Http::withToken($this->apiKey ?? '')
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/models");

                if (! $response->successful()) {
                    return $this->fallbackModels();
                }

                $json = $response->json();
                $items = is_array($json) && isset($json['data']) && is_array($json['data'])
                    ? $json['data']
                    : (is_array($json) ? $json : []);

                if (empty($items)) {
                    return $this->fallbackModels();
                }

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

                if (empty($grouped)) {
                    return $this->fallbackModels();
                }

                // Urutkan model di dalam tiap provider
                foreach ($grouped as $provider => &$models) {
                    sort($models);
                }
                unset($models);

                // Urutkan provider dengan urutan prioritas
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
            } catch (Throwable) {
                return $this->fallbackModels();
            }
        });
    }

    /**
     * Generate soal kuis menggunakan 9router /chat/completions.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function generateQuestions(array $params): array
    {
        $model = (string) ($params['model'] ?? $this->defaultModel);
        $rpsText = (string) ($params['rps_text'] ?? '');
        $topik = (string) ($params['topik'] ?? '');
        $jumlahPg = (int) ($params['jumlah_pg'] ?? 0);
        $jumlahEssay = (int) ($params['jumlah_essay'] ?? 0);
        $level = (string) ($params['level'] ?? 'C3 - Penerapan');

        if ($jumlahPg <= 0 && $jumlahEssay <= 0) {
            throw new RuntimeException('Jumlah soal pilihan ganda atau essay minimal 1.');
        }

        $systemPrompt = <<<PROMPT
Anda adalah asisten pembuat soal akademik perguruan tinggi profesional.
Tugas Anda adalah membuat soal kuis berkualitas tinggi berdasarkan RPS/CPMK dan topik yang diberikan.

ATURAN WAJIB:
1. Kembalikan HANYA format JSON valid tanpa narasi, teks pengantar, atau penutup apapun.
2. Skema JSON harus berstruktur:
{
  "questions": [
    {
      "teks": "Teks soal pertanyaan...",
      "tipe": "pilihan_ganda",
      "bobot": 1,
      "durasi_detik": 60,
      "pembahasan": "Penjelasan kunci jawaban...",
      "opsi": [
        {"teks": "Pilihan A", "is_correct": true},
        {"teks": "Pilihan B", "is_correct": false},
        {"teks": "Pilihan C", "is_correct": false},
        {"teks": "Pilihan D", "is_correct": false}
      ]
    },
    {
      "teks": "Teks pertanyaan essay...",
      "tipe": "essay",
      "bobot": 5,
      "durasi_detik": 120,
      "pembahasan": "Rubrik penilaian essay...",
      "kunci_essay": "Kata kunci jawaban essay..."
    }
  ]
}
3. Untuk tipe "pilihan_ganda", WAJIB menyediakan tepat 4 opsi jawaban dengan TEPAT SATU opsi bernilai "is_correct": true.
4. Bobot default pilihan ganda = 1, essay = 5. Durasi detik default pilihan ganda = 60, essay = 120.
5. Soal harus relevan dengan tingkat Taksonomi Bloom yang diminta.
PROMPT;

        $userPrompt = "Topik Kajian: {$topik}\n";
        $userPrompt .= "Tingkat Kesulitan: {$level}\n";
        $userPrompt .= "Jumlah Pilihan Ganda: {$jumlahPg}\n";
        $userPrompt .= "Jumlah Essay: {$jumlahEssay}\n\n";
        $userPrompt .= "Konteks Silabus / RPS / CPMK:\n{$rpsText}";

        try {
            $response = Http::withToken($this->apiKey ?? '')
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.7,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Gagal menghubungi layanan AI: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new RuntimeException("Layanan AI merespon dengan status {$status}: {$body}");
        }

        $rawContent = $response->json('choices.0.message.content');
        if (! is_string($rawContent) || trim($rawContent) === '') {
            throw new RuntimeException('Layanan AI mengembalikan konten kosong.');
        }

        $cleanedJson = $this->extractJson($rawContent);
        $data = json_decode($cleanedJson, true);

        if (! is_array($data) || ! isset($data['questions']) || ! is_array($data['questions'])) {
            throw new RuntimeException('Format respon AI tidak sesuai dengan struktur JSON soal.');
        }

        return $this->sanitizeQuestions($data['questions']);
    }

    /**
     * Mengekstrak JSON dari teks string jika dibungkus code block markdown.
     */
    protected function extractJson(string $raw): string
    {
        $raw = trim($raw);

        // Jika dibungkus codeblock markdown, kupas pembungkus paling luar
        if (preg_match('/^```(?:json)?\s*([\s\S]*)\s*```$/i', $raw, $matches)) {
            $raw = trim($matches[1]);
        }

        // Cari substring antara kurung kurawal terluar (pertama dan terakhir)
        $firstBrace = strpos($raw, '{');
        $lastBrace = strrpos($raw, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            return substr($raw, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $raw;
    }

    /**
     * Memastikan struktur array soal lengkap dan aman.
     *
     * @param array<int, mixed> $questions
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeQuestions(array $questions): array
    {
        $sanitized = [];

        foreach ($questions as $q) {
            if (! is_array($q) || empty($q['teks'])) {
                continue;
            }

            $tipe = ($q['tipe'] ?? '') === 'essay' ? 'essay' : 'pilihan_ganda';
            $bobot = max(1, (int) ($q['bobot'] ?? ($tipe === 'essay' ? 5 : 1)));
            $durasi = max(15, (int) ($q['durasi_detik'] ?? ($tipe === 'essay' ? 120 : 60)));

            $item = [
                'teks' => trim((string) $q['teks']),
                'tipe' => $tipe,
                'bobot' => $bobot,
                'durasi_detik' => $durasi,
                'pembahasan' => isset($q['pembahasan']) ? trim((string) $q['pembahasan']) : null,
            ];

            if ($tipe === 'pilihan_ganda') {
                $rawOptions = is_array($q['opsi'] ?? null) ? $q['opsi'] : [];
                $options = [];
                $hasCorrect = false;

                foreach ($rawOptions as $opt) {
                    if (! is_array($opt) || ! isset($opt['teks'])) {
                        continue;
                    }
                    $isCorrect = ! empty($opt['is_correct']);
                    if ($isCorrect && ! $hasCorrect) {
                        $hasCorrect = true;
                    } elseif ($isCorrect && $hasCorrect) {
                        $isCorrect = false; // pastikan hanya 1 kunci benar
                    }

                    $options[] = [
                        'teks' => trim((string) $opt['teks']),
                        'is_correct' => $isCorrect,
                    ];
                }

                // Jika tidak ada kunci yang benar, jadikan opsi pertama benar
                if (! $hasCorrect && count($options) > 0) {
                    $options[0]['is_correct'] = true;
                }

                $item['opsi'] = $options;
                $item['kunci_essay'] = null;
            } else {
                $item['kunci_essay'] = isset($q['kunci_essay']) ? trim((string) $q['kunci_essay']) : null;
                $item['opsi'] = [];
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    /**
     * Deteksi nama provider dari ID model atau atribut owned_by.
     */
    protected function detectProvider(string $id, string $ownedBy): string
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

    /**
     * Fallback default model jika discovery gagal.
     *
     * @return array<string, array<string>>
     */
    protected function fallbackModels(): array
    {
        return [
            'Default' => [$this->defaultModel],
        ];
    }
}
