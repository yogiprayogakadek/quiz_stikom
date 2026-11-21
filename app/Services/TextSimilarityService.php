<?php

namespace App\Services;

class TextSimilarityService
{
    /**
     * Hitung koefisien kemiripan teks Jaccard (0.0 sampai 1.0).
     */
    public function similarity(string $a, string $b): float
    {
        $normA = $this->normalize($a);
        $normB = $this->normalize($b);

        if ($normA === '' || $normB === '') {
            return 0.0;
        }

        if ($normA === $normB) {
            return 1.0;
        }

        $tokensA = $this->tokenize($normA);
        $tokensB = $this->tokenize($normB);

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        $countUnion = count($union);

        if ($countUnion === 0) {
            return 0.0;
        }

        return count($intersection) / $countUnion;
    }

    /**
     * Hitung kemiripan dalam persentase bulat (0 sampai 100).
     */
    public function comparePair(string $a, string $b): int
    {
        return (int) round($this->similarity($a, $b) * 100);
    }

    /**
     * Membandingkan daftar jawaban dan mengembalikan peta hasil per id jawaban.
     *
     * @param  array<int, array{id: int|string, student_name?: string, text: string}>  $answers
     * @return array<int|string, array{highest_score: int|null, matches: array<int, array{id: int|string, student_name: string, score: int}>}>
     */
    public function findMatches(array $answers): array
    {
        $count = count($answers);

        if ($count === 0) {
            return [];
        }

        if ($count === 1) {
            $item = $answers[0];

            return [
                $item['id'] => [
                    'highest_score' => null,
                    'matches' => [],
                ],
            ];
        }

        $preprocessed = [];

        foreach ($answers as $item) {
            $id = $item['id'];
            $norm = $this->normalize($item['text'] ?? '');
            $preprocessed[$id] = [
                'id' => $id,
                'student_name' => (string) ($item['student_name'] ?? 'Peserta #'.$id),
                'norm' => $norm,
                'tokens' => $this->tokenize($norm),
            ];
        }

        $matchesByItem = [];

        foreach ($preprocessed as $id => $data) {
            $matchesByItem[$id] = [];
        }

        $keys = array_keys($preprocessed);
        $total = count($keys);

        for ($i = 0; $i < $total; $i++) {
            $idA = $keys[$i];
            $itemA = $preprocessed[$idA];

            for ($j = $i + 1; $j < $total; $j++) {
                $idB = $keys[$j];
                $itemB = $preprocessed[$idB];

                $score = $this->scoreTokens($itemA['norm'], $itemB['norm'], $itemA['tokens'], $itemB['tokens']);

                $matchesByItem[$idA][] = [
                    'id' => $idB,
                    'student_name' => $itemB['student_name'],
                    'score' => $score,
                ];

                $matchesByItem[$idB][] = [
                    'id' => $idA,
                    'student_name' => $itemA['student_name'],
                    'score' => $score,
                ];
            }
        }

        $results = [];

        foreach ($matchesByItem as $id => $matches) {
            usort($matches, fn (array $x, array $y): int => $y['score'] <=> $x['score']);

            $results[$id] = [
                'highest_score' => $matches !== [] ? $matches[0]['score'] : null,
                'matches' => $matches,
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $tokensA
     * @param  array<int, string>  $tokensB
     */
    private function scoreTokens(string $normA, string $normB, array $tokensA, array $tokensB): int
    {
        if ($normA === '' || $normB === '') {
            return 0;
        }

        if ($normA === $normB) {
            return 100;
        }

        if ($tokensA === [] || $tokensB === []) {
            return 0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));
        $countUnion = count($union);

        if ($countUnion === 0) {
            return 0;
        }

        return (int) round((count($intersection) / $countUnion) * 100);
    }

    /**
     * Normalisasi string untuk perbandingan teks.
     */
    public function normalize(string $text): string
    {
        $stripped = strip_tags($text);

        // Hapus format markdown umum (*, _, `, ~, #, >, [, ])
        $cleanMarkdown = preg_replace('/[*_`~#>\[\]\(\)]+/', ' ', $stripped) ?? $stripped;

        // Lowercase
        $lower = mb_strtolower($cleanMarkdown, 'UTF-8');

        // Hapus tanda baca selain huruf dan angka
        $noPunct = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower) ?? $lower;

        // Rapatkan spasi berlebih
        return trim((string) preg_replace('/\s+/u', ' ', $noPunct));
    }

    /**
     * Tokenisasi N-Gram (kata 3-gram atau karakter 4-gram untuk teks pendek).
     *
     * @return array<int, string>
     */
    public function tokenize(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return [];
        }

        $wordCount = count($words);

        // Jika jumlah kata >= 3, buat word 3-gram
        if ($wordCount >= 3) {
            $shingles = [];

            for ($i = 0; $i <= $wordCount - 3; $i++) {
                $shingles[] = $words[$i].' '.$words[$i + 1].' '.$words[$i + 2];
            }

            return array_values(array_unique($shingles));
        }

        // Teks pendek: gunakan character 4-gram atau seluruh kata
        $compact = implode(' ', $words);
        $len = mb_strlen($compact, 'UTF-8');

        if ($len < 4) {
            return [$compact];
        }

        $charGrams = [];

        for ($i = 0; $i <= $len - 4; $i++) {
            $charGrams[] = mb_substr($compact, $i, 4, 'UTF-8');
        }

        return array_values(array_unique($charGrams));
    }
}
