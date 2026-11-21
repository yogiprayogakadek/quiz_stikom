<?php

namespace Tests\Unit;

use App\Services\TextSimilarityService;
use Tests\TestCase;

class TextSimilarityServiceTest extends TestCase
{
    public function test_similarity_returns_100_for_identical_normalized_texts(): void
    {
        $service = new TextSimilarityService();
        $this->assertSame(100, $service->comparePair('Halo dunia!', 'halo   dunia'));
        $this->assertSame(100, $service->comparePair('<p>Algoritma **Dijkstra**</p>', 'algoritma dijkstra'));
    }

    public function test_similarity_returns_0_for_empty_or_completely_different_texts(): void
    {
        $service = new TextSimilarityService();
        $this->assertSame(0, $service->comparePair('', ''));
        $this->assertSame(0, $service->comparePair('abc', ''));
        $this->assertSame(0, $service->comparePair('', 'xyz'));
    }

    public function test_find_matches_detects_highest_similarity_between_answers(): void
    {
        $service = new TextSimilarityService();
        $items = [
            [
                'id' => 1,
                'student_name' => 'Peserta 1',
                'text' => 'Algoritma Dijkstra mencari jalur terpendek dari satu titik ke titik lain dalam graf berbobot positif secara efisien.',
            ],
            [
                'id' => 2,
                'student_name' => 'Peserta 2',
                'text' => 'Algoritma Dijkstra mencari jalur terpendek dari satu titik ke titik lain dalam graf berbobot positif dengan baik.',
            ],
            [
                'id' => 3,
                'student_name' => 'Peserta 3',
                'text' => 'Fotosintesis adalah proses pembentukan karbohidrat oleh tumbuhan berklorofil menggunakan energi cahaya matahari.',
            ],
        ];

        $results = $service->findMatches($items);

        $this->assertArrayHasKey(1, $results);
        $this->assertArrayHasKey(2, $results);
        $this->assertArrayHasKey(3, $results);

        $this->assertGreaterThanOrEqual(70, $results[1]['highest_score']);
        $this->assertSame(2, $results[1]['matches'][0]['id']);
        $this->assertSame('Peserta 2', $results[1]['matches'][0]['student_name']);

        $this->assertGreaterThanOrEqual(70, $results[2]['highest_score']);
        $this->assertSame(1, $results[2]['matches'][0]['id']);

        $this->assertLessThan(20, $results[3]['highest_score']);
    }

    public function test_find_matches_handles_empty_or_single_answer_gracefully(): void
    {
        $service = new TextSimilarityService();
        $this->assertSame([], $service->findMatches([]));

        $single = [['id' => 10, 'student_name' => 'Solo', 'text' => 'Hanya ada satu jawaban']];
        $res = $service->findMatches($single);
        $this->assertNull($res[10]['highest_score']);
        $this->assertSame([], $res[10]['matches']);
    }

    public function test_find_matches_includes_text_and_excludes_zero_scores(): void
    {
        $service = new TextSimilarityService();
        $answers = [
            ['id' => 1, 'user_id' => 1, 'student_name' => 'Ali', 'text' => 'Algoritma Dijkstra mencari lintasan terpendek graf.'],
            ['id' => 2, 'user_id' => 2, 'student_name' => 'Budi', 'text' => 'Algoritma Dijkstra menemukan rute lintasan terpendek graf berbobot.'],
            ['id' => 3, 'user_id' => 3, 'student_name' => 'Cici', 'text' => 'Bunga mawar merah harum mewangi sepanjang hari.'],
        ];

        $matches = $service->findMatches($answers);

        // Ali and Budi should match and contain text
        $this->assertNotEmpty($matches[1]['matches']);
        $this->assertArrayHasKey('text', $matches[1]['matches'][0]);
        $this->assertSame('Algoritma Dijkstra menemukan rute lintasan terpendek graf berbobot.', $matches[1]['matches'][0]['text']);

        // Ali and Cici share 0 similarity, so Cici should not be in Ali's matches
        $matchedIds = array_column($matches[1]['matches'], 'id');
        $this->assertNotContains(3, $matchedIds);
    }
}
