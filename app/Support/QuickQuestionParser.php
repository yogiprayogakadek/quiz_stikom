<?php

namespace App\Support;

use Illuminate\Support\Str;

class QuickQuestionParser
{
    /**
     * @param  array<int, string>  $allowedTypes
     * @return array{valid: array<int, array<string, mixed>>, errors: array<int, array{nomor: int, potongan: string, alasan: string}>}
     */
    public static function parse(string $rawText, array $allowedTypes = ['pilihan_ganda', 'essay', 'pilihan_majemuk', 'isian_singkat']): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawText));
        if ($normalized === '') {
            return ['valid' => [], 'errors' => []];
        }

        $blocks = self::splitBlocks($normalized);
        $valid = [];
        $errors = [];

        foreach ($blocks as $idx => $blockLines) {
            $nomor = $idx + 1;
            $parsed = self::parseBlock($blockLines, $nomor, $allowedTypes);

            if (isset($parsed['error'])) {
                $errors[] = [
                    'nomor' => $nomor,
                    'potongan' => Str::limit(implode(' ', $blockLines), 100),
                    'alasan' => $parsed['error'],
                ];
            } else {
                $valid[] = $parsed['item'];
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function splitBlocks(string $text): array
    {
        $lines = explode("\n", $text);
        $blocks = [];
        $current = [];
        $hasOptionsOrMeta = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($current !== []) {
                    $blocks[] = $current;
                    $current = [];
                    $hasOptionsOrMeta = false;
                }

                continue;
            }

            // Pisahkan otomatis bila soal bernomor baru muncul setelah opsi atau kunci soal sebelumnya.
            if ($current !== [] && $hasOptionsOrMeta && preg_match('/^(?:no\.?\s*)?\d+[\.\)]\s+\S+/iu', $trimmed)) {
                $blocks[] = $current;
                $current = [$trimmed];
                $hasOptionsOrMeta = false;

                continue;
            }

            if (
                preg_match('/^\*?\s*[A-Fa-f][\.\)]\s+\S+/u', $trimmed)
                || preg_match('/^(?:kunci(?:\s+jawaban)?|jawaban(?:\s+benar)?|pembahasan)\s*:/iu', $trimmed)
            ) {
                $hasOptionsOrMeta = true;
            }

            $current[] = $trimmed;
        }

        if ($current !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $allowedTypes
     * @return array{item?: array<string, mixed>, error?: string}
     */
    private static function parseBlock(array $lines, int $nomor, array $allowedTypes): array
    {
        $firstLine = array_shift($lines) ?? '';
        $firstLine = preg_replace('/^(?:no\.?\s*)?\d+[\.\)]\s*/iu', '', $firstLine) ?? $firstLine;

        $tipe = null;
        $bobot = null;
        $durasi = null;
        $scoringMode = 'all_or_nothing';
        $caseSensitive = false;

        if (preg_match('/^\[([^\]]+)\]\s*(.*)$/u', $firstLine, $matches)) {
            self::applyHeaderMeta($matches[1], $tipe, $bobot, $durasi, $scoringMode, $caseSensitive);
            $firstLine = trim($matches[2]);
        }

        $stemLines = $firstLine !== '' ? [$firstLine] : [];
        $rawOptions = [];
        $starredIndices = [];
        $explicitKey = null;
        $pembahasan = null;
        $startedOptions = false;

        foreach ($lines as $line) {
            if (preg_match('/^bobot\s*:\s*(\d+)\s*$/iu', $line, $m)) {
                $bobot = (int) $m[1];

                continue;
            }

            if (preg_match('/^durasi(?:\s+detik)?\s*:\s*(\d+)\s*$/iu', $line, $m)) {
                $durasi = (int) $m[1];

                continue;
            }

            if (preg_match('/^pembahasan\s*:\s*(.+)$/iu', $line, $m)) {
                $pembahasan = trim($m[1]);

                continue;
            }

            if (preg_match('/^(?:kunci(?:\s+jawaban)?|jawaban(?:\s+benar)?)\s*:\s*(.+)$/iu', $line, $m)) {
                $explicitKey = trim($m[1]);

                continue;
            }

            if (preg_match('/^(\*\s*)?([A-Fa-f])[\.\)]\s*(.+)$/u', $line, $m)) {
                $startedOptions = true;
                $letter = strtoupper($m[2]);
                $optText = trim($m[3]);
                $isStarred = trim($m[1]) !== '';

                if (preg_match('/^(.*?)\s*(?:\[(?:benar|kunci)\]|\((?:benar|kunci)\))\s*$/iu', $optText, $suffixMatch)) {
                    $optText = trim($suffixMatch[1]);
                    $isStarred = true;
                }

                $idx = count($rawOptions);
                $rawOptions[] = [
                    'letter' => $letter,
                    'teks' => $optText,
                ];

                if ($isStarred) {
                    $starredIndices[] = $idx;
                }

                continue;
            }

            if (! $startedOptions) {
                $stemLines[] = $line;
            }
        }

        $teks = trim(implode("\n", $stemLines));
        if ($teks === '') {
            return ['error' => 'Teks pertanyaan kosong.'];
        }

        if (mb_strlen($teks) > 2000) {
            return ['error' => 'Teks pertanyaan melebihi 2000 karakter.'];
        }

        if ($tipe === null) {
            $tipe = $rawOptions !== [] ? 'pilihan_ganda' : 'essay';
        }

        if (! in_array($tipe, $allowedTypes, true)) {
            return ['error' => "Tipe soal '{$tipe}' belum didukung pada mode ini."];
        }

        $bobotFinal = $bobot ?? ($tipe === 'essay' ? 10 : 1);
        $durasiFinal = $durasi ?? ($tipe === 'essay' ? 120 : 30);

        if ($bobotFinal < 1 || $bobotFinal > 1000) {
            return ['error' => 'Bobot soal harus antara 1 sampai 1000.'];
        }

        if ($durasiFinal < 5 || $durasiFinal > 1800) {
            return ['error' => 'Durasi soal harus antara 5 sampai 1800 detik.'];
        }

        if ($tipe === 'essay') {
            return [
                'item' => [
                    'nomor' => $nomor,
                    'teks' => $teks,
                    'tipe' => 'essay',
                    'bobot' => $bobotFinal,
                    'durasi_detik' => $durasiFinal,
                    'opsi' => [],
                    'correct' => null,
                    'correct_indices' => [],
                    'kunci_essay' => $explicitKey !== '' ? $explicitKey : null,
                    'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                    'answer_config' => null,
                ],
            ];
        }

        if ($tipe === 'isian_singkat') {
            $acceptedRaw = $explicitKey ?? '';
            $accepted = array_values(array_filter(
                array_map('trim', preg_split('/[|;\n]+/', $acceptedRaw) ?: []),
                fn (string $v) => $v !== ''
            ));

            if ($accepted === []) {
                return ['error' => 'Soal isian singkat wajib memiliki baris Kunci: jawaban yang diterima.'];
            }

            return [
                'item' => [
                    'nomor' => $nomor,
                    'teks' => $teks,
                    'tipe' => 'isian_singkat',
                    'bobot' => $bobotFinal,
                    'durasi_detik' => $durasiFinal,
                    'opsi' => array_map(fn (string $ans) => ['teks' => $ans, 'is_correct' => true], $accepted),
                    'correct' => 0,
                    'correct_indices' => array_keys($accepted),
                    'kunci_essay' => null,
                    'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                    'answer_config' => ['case_sensitive' => $caseSensitive],
                ],
            ];
        }

        if (count($rawOptions) < 2 || count($rawOptions) > 6) {
            return ['error' => 'Soal objektif membutuhkan 2 sampai 6 pilihan jawaban (A-F).'];
        }

        $correctIndices = $starredIndices;
        if ($explicitKey !== null && $explicitKey !== '') {
            $resolvedFromKey = self::resolveKeyIndices($explicitKey, $rawOptions);
            if ($resolvedFromKey === []) {
                return ['error' => "Kunci jawaban '{$explicitKey}' tidak cocok dengan pilihan yang tersedia."];
            }
            $correctIndices = array_values(array_unique(array_merge($correctIndices, $resolvedFromKey)));
        }

        if ($tipe === 'pilihan_ganda') {
            if (count($correctIndices) === 0) {
                return ['error' => 'Tandai satu jawaban benar dengan tanda bintang (*B. ...) atau baris Kunci: B.'];
            }
            if (count($correctIndices) > 1) {
                return ['error' => 'Pilihan ganda hanya boleh memiliki satu kunci benar. Gunakan tag [Pilihan Majemuk] untuk multi-jawaban.'];
            }

            $correctIndex = $correctIndices[0];

            return [
                'item' => [
                    'nomor' => $nomor,
                    'teks' => $teks,
                    'tipe' => 'pilihan_ganda',
                    'bobot' => $bobotFinal,
                    'durasi_detik' => $durasiFinal,
                    'opsi' => array_values(array_map(
                        fn (array $opt, int $i) => [
                            'teks' => $opt['teks'],
                            'is_correct' => $i === $correctIndex,
                        ],
                        $rawOptions,
                        array_keys($rawOptions)
                    )),
                    'correct' => $correctIndex,
                    'correct_indices' => [$correctIndex],
                    'kunci_essay' => null,
                    'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                    'answer_config' => null,
                ],
            ];
        }

        if (count($correctIndices) < 1) {
            return ['error' => 'Soal pilihan majemuk wajib memiliki minimal satu jawaban benar.'];
        }

        return [
            'item' => [
                'nomor' => $nomor,
                'teks' => $teks,
                'tipe' => 'pilihan_majemuk',
                'bobot' => $bobotFinal,
                'durasi_detik' => $durasiFinal,
                'opsi' => array_values(array_map(
                    fn (array $opt, int $i) => [
                        'teks' => $opt['teks'],
                        'is_correct' => in_array($i, $correctIndices, true),
                    ],
                    $rawOptions,
                    array_keys($rawOptions)
                )),
                'correct' => $correctIndices[0],
                'correct_indices' => $correctIndices,
                'kunci_essay' => null,
                'pembahasan' => $pembahasan !== '' ? $pembahasan : null,
                'answer_config' => ['scoring_mode' => $scoringMode],
            ],
        ];
    }

    private static function applyHeaderMeta(
        string $header,
        ?string &$tipe,
        ?int &$bobot,
        ?int &$durasi,
        string &$scoringMode,
        bool &$caseSensitive
    ): void {
        $parts = array_map('trim', explode('|', $header));

        foreach ($parts as $part) {
            $lower = Str::lower($part);

            if (in_array($lower, ['essay', 'esai', 'uraian'], true)) {
                $tipe = 'essay';
            } elseif (in_array($lower, ['pg', 'pilihan ganda', 'pilihan_ganda'], true)) {
                $tipe = 'pilihan_ganda';
            } elseif (in_array($lower, ['pilihan majemuk', 'pilihan_majemuk', 'majemuk', 'multi'], true)) {
                $tipe = 'pilihan_majemuk';
            } elseif (in_array($lower, ['isian singkat', 'isian_singkat', 'isian'], true)) {
                $tipe = 'isian_singkat';
            } elseif (in_array($lower, ['parsial', 'partial'], true)) {
                $scoringMode = 'partial';
            } elseif (in_array($lower, ['case_sensitive', 'peka huruf'], true)) {
                $caseSensitive = true;
            } elseif (preg_match('/^bobot\s*:\s*(\d+)$/iu', $part, $m)) {
                $bobot = (int) $m[1];
            } elseif (preg_match('/^durasi\s*:\s*(\d+)$/iu', $part, $m)) {
                $durasi = (int) $m[1];
            }
        }
    }

    /**
     * @param  array<int, array{letter: string, teks: string}>  $rawOptions
     * @return array<int, int>
     */
    private static function resolveKeyIndices(string $keyRaw, array $rawOptions): array
    {
        $tokens = array_values(array_filter(
            array_map('trim', preg_split('/[,;\s]+/', $keyRaw) ?: []),
            fn (string $v) => $v !== ''
        ));

        $indices = [];
        foreach ($tokens as $token) {
            $upper = strtoupper(rtrim($token, '.)'));
            foreach ($rawOptions as $idx => $opt) {
                if ($opt['letter'] === $upper) {
                    $indices[] = $idx;

                    continue 2;
                }
            }

            if (is_numeric($token)) {
                $numIdx = ((int) $token) - 1;
                if (isset($rawOptions[$numIdx])) {
                    $indices[] = $numIdx;
                }
            }
        }

        if ($indices === []) {
            foreach ($rawOptions as $idx => $opt) {
                if (Str::lower($opt['teks']) === Str::lower(trim($keyRaw))) {
                    $indices[] = $idx;
                    break;
                }
            }
        }

        return array_values(array_unique($indices));
    }
}
