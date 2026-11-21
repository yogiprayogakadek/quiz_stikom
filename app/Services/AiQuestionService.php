<?php

namespace App\Services;

use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiQuestionService
{
    protected ?AiManager $manager;

    public function __construct(?AiManager $ai = null)
    {
        $this->manager = $ai;
    }

    protected function ai(): AiManager
    {
        return $this->manager ??= new AiManager;
    }

    /**
     * Mendapatkan daftar model AI yang tersedia dari provider aktif, dikelompokkan per provider.
     *
     * @return array<string, array<string>>
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        $cacheKey = 'ai_models_'.sha1($this->ai()->config()->textBaseUrl());

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 3600, function () {
            try {
                return $this->ai()->text()->models(10) ?: $this->fallbackModels();
            } catch (Throwable) {
                return $this->fallbackModels();
            }
        });
    }

    /**
     * Generate soal kuis menggunakan 9router /chat/completions.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     *
     * @throws RuntimeException
     */
    public function generateQuestions(array $params): array
    {
        $model = (string) ($params['model'] ?? $this->ai()->config()->defaultModel());
        $rpsText = (string) ($params['rps_text'] ?? '');
        $topik = (string) ($params['topik'] ?? '');
        $jumlahPg = (int) ($params['jumlah_pg'] ?? 0);
        $jumlahEssay = (int) ($params['jumlah_essay'] ?? 0);
        $nilaiMaksimal = max(10, (int) ($params['nilai_maksimal'] ?? 100));
        $porsiPg = min(90, max(10, (int) ($params['porsi_pg'] ?? 60)));

        $rawLevel = $params['level'] ?? ['C3 - Menerapkan'];
        $levelStr = is_array($rawLevel) ? implode(', ', array_filter($rawLevel)) : (string) $rawLevel;
        if (trim($levelStr) === '') {
            $levelStr = 'C3 - Menerapkan';
        }

        if ($jumlahPg <= 0 && $jumlahEssay <= 0) {
            throw new RuntimeException('Jumlah soal pilihan ganda atau essay minimal 1.');
        }

        $weights = self::calculateWeights($nilaiMaksimal, $jumlahPg, $jumlahEssay, $porsiPg);

        $systemPrompt = <<<'PROMPT'
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
        $userPrompt .= "Tingkat Kesulitan / Taksonomi Bloom: {$levelStr}\n";
        $userPrompt .= "Jumlah Pilihan Ganda: {$jumlahPg}\n";
        $userPrompt .= "Jumlah Essay: {$jumlahEssay}\n\n";
        $userPrompt .= "Konteks Silabus / RPS / CPMK:\n{$rpsText}";

        $rawContent = $this->ai()->text()->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $model, 0.7);

        $cleanedJson = $this->extractJson($rawContent);
        $data = json_decode($cleanedJson, true);

        if (! is_array($data) || ! isset($data['questions']) || ! is_array($data['questions'])) {
            throw new RuntimeException('Format respon AI tidak sesuai dengan struktur JSON soal.');
        }

        return $this->sanitizeQuestions($data['questions'], $weights);
    }

    /**
     * @param  array<int, array{label: string, points: int}>  $criteria
     * @return array{score: int, confidence: int, feedback: string, model: string, provider: string, mode: string}
     */
    public function evaluateEssay(string $question, ?string $referenceAnswer, array $criteria, string $answer, int $maxScore, string $mode = 'seimbang'): array
    {
        $mode = in_array($mode, ['ketat', 'seimbang', 'longgar'], true) ? $mode : 'seimbang';
        $modeInstruction = match ($mode) {
            'ketat' => "Mode penilaian: Ketat.\nBeri poin hanya untuk bagian yang secara eksplisit memenuhi kriteria rubrik. Bagian yang hilang, salah, atau hanya berupa kata kunci tanpa penjelasan tidak mendapat poin.",
            'longgar' => "Mode penilaian: Longgar.\nHargai ide inti yang relevan meskipun jawaban singkat, kurang rapi, atau memiliki detail kecil yang kurang tepat. Toleransi perbedaan redaksi dan kesalahan kecil yang tidak mengubah makna. Jawaban kosong, tidak relevan, atau bertentangan dengan konsep inti tetap tidak mendapat poin.",
            default => "Mode penilaian: Seimbang.\nBeri poin proporsional untuk ide yang benar. Toleransi perbedaan redaksi yang tidak mengubah makna. Bagian penting yang hilang atau kesalahan konsep mengurangi poin.",
        };

        $systemPrompt = <<<PROMPT
Nilai jawaban essay berdasarkan pertanyaan, rubrik, dan nilai maksimum yang diberikan.
{$modeInstruction}
Kualitas tulisan hanya memengaruhi nilai jika rubrik memintanya.
Kembalikan JSON valid saja dengan bentuk:
{"score":0,"confidence":0,"feedback":"Alasan singkat dalam bahasa Indonesia."}
score harus bilangan bulat dari 0 sampai nilai maksimum. confidence harus bilangan bulat dari 0 sampai 100. confidence mengukur keyakinan, bukan tingkat kelonggaran. Jangan menebak fakta di luar rubrik.
PROMPT;
        $criteriaText = $criteria === []
            ? 'Tidak ada kriteria terstruktur.'
            : implode("\n", array_map(
                fn (array $criterion) => '- '.$criterion['label'].' (maksimal '.$criterion['points'].' poin)',
                $criteria,
            ));
        $userPrompt = "Pertanyaan:\n{$question}\n\nJawaban acuan:\n".($referenceAnswer ?: 'Tidak ada jawaban acuan.')."\n\nKriteria:\n{$criteriaText}\n\nJawaban peserta:\n{$answer}\n\nNilai maksimum: {$maxScore}";

        $data = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $prompt = $attempt === 1
                ? $userPrompt
                : $userPrompt."\n\nPENTING: Kembalikan HANYA satu objek JSON valid tanpa markdown dan tanpa teks lain. Wajib memuat kunci score, confidence, dan feedback. Tulis feedback dalam satu baris.";

            $rawContent = $this->ai()->text()->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ], null, 0.1);

            $data = json_decode($this->extractJson($rawContent), true);

            if (is_array($data) && isset($data['score'], $data['confidence'])) {
                break;
            }

            Log::warning('Saran penilaian essay tidak valid.', [
                'attempt' => $attempt,
                'mode' => $mode,
                'model' => $this->ai()->config()->defaultModel(),
                'keys' => is_array($data) ? array_keys($data) : [],
                'preview' => Str::limit($rawContent, 1000),
            ]);

            $data = null;
        }

        if (! is_array($data) || ! isset($data['score'], $data['confidence'])) {
            throw new RuntimeException('Layanan AI tidak mengembalikan format penilaian yang valid.');
        }

        $feedback = is_scalar($data['feedback'] ?? null) ? trim((string) $data['feedback']) : '';

        return [
            'score' => max(0, min($maxScore, (int) $data['score'])),
            'confidence' => max(0, min(100, (int) $data['confidence'])),
            'feedback' => Str::limit($feedback, 1500, '') ?: 'AI tidak memberikan alasan.',
            'model' => $this->ai()->config()->defaultModel(),
            'provider' => parse_url($this->ai()->config()->textBaseUrl(), PHP_URL_HOST) ?: $this->ai()->config()->textBaseUrl(),
            'mode' => $mode,
        ];
    }

    /**
     * Analisis probabilitas jawaban essay dibuat oleh AI (skor 0-100 dan alasan).
     *
     * @return array{score: int, reason: string, model: string, provider: string}
     */
    public function detectAiGenerated(string $question, string $answer): array
    {
        $systemPrompt = <<<PROMPT
Anda adalah sistem audit integritas akademik. Analisis apakah teks jawaban peserta ditulis oleh manusia atau dihasilkan oleh model AI/LLM.
Periksa pola kalimat, burstiness, variasi leksikal, dan transisi khas AI.
Kembalikan HANYA satu objek JSON valid dengan bentuk:
{"score":0,"reason":"Alasan ringkas 1-2 kalimat dalam bahasa Indonesia."}
score adalah bilangan bulat dari 0 (pasti murni manusia) sampai 100 (pasti dibuat oleh AI).
PROMPT;

        $userPrompt = "Pertanyaan:\n{$question}\n\nJawaban peserta:\n{$answer}";

        $data = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $prompt = $attempt === 1
                ? $userPrompt
                : $userPrompt."\n\nPENTING: Kembalikan HANYA format JSON valid tanpa markdown. Wajib memuat kunci score dan reason.";

            $rawContent = $this->ai()->text()->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ], null, 0.1);

            $data = json_decode($this->extractJson($rawContent), true);

            if (is_array($data) && isset($data['score'])) {
                break;
            }

            $data = null;
        }

        if (! is_array($data) || ! isset($data['score'])) {
            throw new RuntimeException('Layanan AI tidak mengembalikan format analisis yang valid.');
        }

        $reason = is_scalar($data['reason'] ?? null) ? trim((string) $data['reason']) : '';

        return [
            'score' => max(0, min(100, (int) $data['score'])),
            'reason' => Str::limit($reason, 1500, '') ?: 'AI tidak memberikan alasan.',
            'model' => $this->ai()->config()->defaultModel(),
            'provider' => parse_url($this->ai()->config()->textBaseUrl(), PHP_URL_HOST) ?: $this->ai()->config()->textBaseUrl(),
        ];
    }

    /**
     * Membagi total nilai maksimal kuis ke tiap butir soal PG dan Essay.
     *
     * @return array{pg: array<int>, essay: array<int>, total: int}
     */
    public static function calculateWeights(int $nilaiMaksimal, int $jumlahPg, int $jumlahEssay, int $porsiPg = 60): array
    {
        $nilaiMaksimal = max(10, $nilaiMaksimal);
        $jumlahPg = max(0, $jumlahPg);
        $jumlahEssay = max(0, $jumlahEssay);

        if ($jumlahPg === 0 && $jumlahEssay === 0) {
            return ['pg' => [], 'essay' => [], 'total' => 0];
        }

        if ($jumlahEssay === 0) {
            $weightsPg = self::distributeAmount($nilaiMaksimal, $jumlahPg);

            return [
                'pg' => $weightsPg,
                'essay' => [],
                'total' => array_sum($weightsPg),
            ];
        }

        if ($jumlahPg === 0) {
            $weightsEssay = self::distributeAmount($nilaiMaksimal, $jumlahEssay);

            return [
                'pg' => [],
                'essay' => $weightsEssay,
                'total' => array_sum($weightsEssay),
            ];
        }

        // Keduanya ada: gunakan rasio porsi_pg
        $porsiPg = min(90, max(10, $porsiPg));
        $targetPg = (int) round($nilaiMaksimal * ($porsiPg / 100));
        // Pastikan minimal 1 poin per butir
        $targetPg = max($jumlahPg, min($nilaiMaksimal - $jumlahEssay, $targetPg));
        $targetEssay = $nilaiMaksimal - $targetPg;

        $weightsPg = self::distributeAmount($targetPg, $jumlahPg);
        $weightsEssay = self::distributeAmount($targetEssay, $jumlahEssay);

        return [
            'pg' => $weightsPg,
            'essay' => $weightsEssay,
            'total' => array_sum($weightsPg) + array_sum($weightsEssay),
        ];
    }

    /**
     * Membagi sejumlah nilai secara merata ke N item dengan membagi sisa modulo ke item awal.
     *
     * @return array<int>
     */
    protected static function distributeAmount(int $total, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $total = max($count, $total);
        $base = intdiv($total, $count);
        $rem = $total % $count;

        $weights = [];
        for ($i = 0; $i < $count; $i++) {
            $weights[] = $base + ($i < $rem ? 1 : 0);
        }

        return $weights;
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
     * Memastikan struktur array soal lengkap dan aman serta menerapkan alokasi bobot proporsional.
     *
     * @param  array<int, mixed>  $questions
     * @param  array{pg?: array<int>, essay?: array<int>}|null  $weights
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeQuestions(array $questions, ?array $weights = null): array
    {
        $sanitized = [];
        $pgIndex = 0;
        $essayIndex = 0;

        foreach ($questions as $q) {
            if (! is_array($q) || empty($q['teks'])) {
                continue;
            }

            $tipe = ($q['tipe'] ?? '') === 'essay' ? 'essay' : 'pilihan_ganda';

            if ($tipe === 'pilihan_ganda') {
                $bobot = isset($weights['pg'][$pgIndex])
                    ? $weights['pg'][$pgIndex]
                    : max(1, (int) ($q['bobot'] ?? 1));
                $pgIndex++;
            } else {
                $bobot = isset($weights['essay'][$essayIndex])
                    ? $weights['essay'][$essayIndex]
                    : max(1, (int) ($q['bobot'] ?? 5));
                $essayIndex++;
            }

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
     * Fallback default model jika discovery gagal.
     *
     * @return array<string, array<string>>
     */
    protected function fallbackModels(): array
    {
        return [
            'Default' => [$this->ai()->config()->defaultModel()],
        ];
    }
}
