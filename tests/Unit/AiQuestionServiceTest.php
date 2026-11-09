<?php

namespace Tests\Unit;

use App\Services\AiQuestionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiQuestionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.ai.base_url' => 'https://api.9router.com/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'ag/gemini-3.8-flash-medium',
            'services.ai.timeout' => 10,
        ]);
    }

    public function test_get_available_models_groups_by_provider(): void
    {
        Http::fake([
            'https://api.9router.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'ag/gemini-3.8-flash-medium', 'owned_by' => 'google'],
                    ['id' => 'google/gemini-1.5-pro', 'owned_by' => 'google'],
                    ['id' => 'openai/gpt-4o-mini', 'owned_by' => 'openai'],
                    ['id' => 'anthropic/claude-3-5-sonnet', 'owned_by' => 'anthropic'],
                    ['id' => 'custom-model', 'owned_by' => 'system'],
                ],
            ], 200),
        ]);

        $service = new AiQuestionService();
        $models = $service->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertArrayHasKey('Google', $models);
        $this->assertArrayHasKey('OpenAI', $models);
        $this->assertArrayHasKey('Anthropic', $models);
        $this->assertContains('ag/gemini-3.8-flash-medium', $models['Google']);
        $this->assertContains('openai/gpt-4o-mini', $models['OpenAI']);

        // Check caching: Next call should use cache without hitting Http again
        $this->assertTrue(Cache::has('9router_models'));

        // Force refresh should forget cache
        $service->getAvailableModels(forceRefresh: true);
        $this->assertTrue(Cache::has('9router_models'));
    }

    public function test_get_available_models_fallback_on_failure(): void
    {
        Http::fake([
            'https://api.9router.com/v1/models' => Http::response(null, 500),
        ]);

        $service = new AiQuestionService();
        $models = $service->getAvailableModels();

        $this->assertIsArray($models);
        $this->assertArrayHasKey('Default', $models);
        $this->assertContains('ag/gemini-3.8-flash-medium', $models['Default']);
    }

    public function test_generate_questions_parses_json_cleanly(): void
    {
        $mockPayload = [
            'questions' => [
                [
                    'teks' => 'Apa itu inheritance?',
                    'tipe' => 'pilihan_ganda',
                    'bobot' => 1,
                    'durasi_detik' => 60,
                    'pembahasan' => 'Inheritance adalah pewarisan sifat.',
                    'opsi' => [
                        ['teks' => 'Pewarisan sifat class', 'is_correct' => true],
                        ['teks' => 'Enkapsulasi data', 'is_correct' => false],
                        ['teks' => 'Polimorfisme fungsi', 'is_correct' => false],
                        ['teks' => 'Abstraksi method', 'is_correct' => false],
                    ],
                ],
                [
                    'teks' => 'Jelaskan konsep polymorphism!',
                    'tipe' => 'essay',
                    'bobot' => 5,
                    'durasi_detik' => 120,
                    'pembahasan' => 'Konsep banyak bentuk.',
                    'kunci_essay' => 'Banyak bentuk, overriding, overloading',
                ],
            ],
        ];

        // AI response sometimes includes markdown code blocks ```json ... ```
        $mockContent = "```json\n" . json_encode($mockPayload) . "\n```";

        Http::fake([
            'https://api.9router.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $mockContent,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new AiQuestionService();
        $result = $service->generateQuestions([
            'model' => 'ag/gemini-3.8-flash-medium',
            'rps_text' => 'Pemrograman Berorientasi Objek',
            'topik' => 'Inheritance dan Polymorphism',
            'jumlah_pg' => 1,
            'jumlah_essay' => 1,
            'level' => 'C3 - Penerapan',
        ]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('pilihan_ganda', $result[0]['tipe']);
        $this->assertEquals('Apa itu inheritance?', $result[0]['teks']);
        $this->assertCount(4, $result[0]['opsi']);
        $this->assertTrue($result[0]['opsi'][0]['is_correct']);
        $this->assertEquals('essay', $result[1]['tipe']);
        $this->assertEquals('Jelaskan konsep polymorphism!', $result[1]['teks']);
    }

    public function test_generate_questions_handles_embedded_code_blocks(): void
    {
        $mockPayload = [
            'questions' => [
                [
                    'teks' => "Perhatikan kode berikut:\n```php\necho 'hello';\n```\nApakah outputnya?",
                    'tipe' => 'pilihan_ganda',
                    'bobot' => 1,
                    'durasi_detik' => 60,
                    'pembahasan' => 'Mencetak hello',
                    'opsi' => [
                        ['teks' => 'hello', 'is_correct' => true],
                        ['teks' => 'world', 'is_correct' => false],
                    ],
                ],
            ],
        ];

        // Wrapped in outer markdown codeblock
        $mockContent = "```json\n" . json_encode($mockPayload) . "\n```";

        Http::fake([
            'https://api.9router.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => $mockContent]],
                ],
            ], 200),
        ]);

        $service = new AiQuestionService();
        $result = $service->generateQuestions([
            'model' => 'ag/gemini-3.8-flash-medium',
            'rps_text' => 'Pemrograman PHP',
            'topik' => 'Sintaks Dasar',
            'jumlah_pg' => 1,
            'jumlah_essay' => 0,
            'level' => 'C3 - Menerapkan',
        ]);

        $this->assertCount(1, $result);
        $this->assertStringContainsString("echo 'hello';", $result[0]['teks']);
    }

    public function test_generate_questions_with_multiple_bloom_levels(): void
    {
        $mockPayload = [
            'questions' => [
                [
                    'teks' => 'Soal C2 dan C4',
                    'tipe' => 'pilihan_ganda',
                    'bobot' => 1,
                    'durasi_detik' => 60,
                    'opsi' => [
                        ['teks' => 'A', 'is_correct' => true],
                        ['teks' => 'B', 'is_correct' => false],
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://api.9router.com/v1/chat/completions' => function ($request) use ($mockPayload) {
                $body = json_decode($request->body(), true);
                $userContent = $body['messages'][1]['content'];

                // Verify multiple bloom levels are included in the prompt
                if (! str_contains($userContent, 'C2 - Memahami, C4 - Menganalisis')) {
                    return Http::response(['error' => 'Level not formatted properly'], 400);
                }

                return Http::response([
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => json_encode($mockPayload)]],
                    ],
                ], 200);
            },
        ]);

        $service = new AiQuestionService();
        $result = $service->generateQuestions([
            'model' => 'ag/gemini-3.8-flash-medium',
            'rps_text' => 'Silabus Jaringan',
            'topik' => 'Routing',
            'jumlah_pg' => 1,
            'jumlah_essay' => 0,
            'level' => ['C2 - Memahami', 'C4 - Menganalisis'],
        ]);

        $this->assertCount(1, $result);
    }

    public function test_generate_questions_sends_stream_false_and_parses_multipart_content(): void
    {
        $mockPayload = [
            'questions' => [
                [
                    'teks' => 'Soal dari multipart',
                    'tipe' => 'essay',
                    'bobot' => 5,
                    'durasi_detik' => 120,
                ],
            ],
        ];

        Http::fake([
            'https://api.9router.com/v1/chat/completions' => function ($request) use ($mockPayload) {
                $body = json_decode($request->body(), true);
                if (isset($body['stream']) && $body['stream'] === true) {
                    return Http::response(['error' => 'stream must be false'], 400);
                }

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => [
                                    ['type' => 'text', 'text' => json_encode($mockPayload)],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = new AiQuestionService();
        $result = $service->generateQuestions([
            'model' => 'ag/gemini-3.8-flash-medium',
            'rps_text' => 'Silabus',
            'topik' => 'Topik',
            'jumlah_pg' => 0,
            'jumlah_essay' => 1,
            'level' => 'C2 - Memahami',
        ]);

        $this->assertCount(1, $result);
        $this->assertEquals('Soal dari multipart', $result[0]['teks']);
    }

    public function test_generate_questions_handles_sse_chunked_response_fallback(): void
    {
        $mockPayload = [
            'questions' => [
                [
                    'teks' => 'Soal dari SSE',
                    'tipe' => 'essay',
                    'bobot' => 5,
                    'durasi_detik' => 120,
                ],
            ],
        ];

        $jsonStr = json_encode($mockPayload);
        $sseBody = "data: {\"choices\":[{\"delta\":{\"content\":\"" . addslashes(substr($jsonStr, 0, 20)) . "\"}}]}\n\n"
            . "data: {\"choices\":[{\"delta\":{\"content\":\"" . addslashes(substr($jsonStr, 20)) . "\"}}]}\n\n"
            . "data: [DONE]\n\n";

        Http::fake([
            'https://api.9router.com/v1/chat/completions' => Http::response($sseBody, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $service = new AiQuestionService();
        $result = $service->generateQuestions([
            'model' => 'ag/gemini-3.8-flash-medium',
            'rps_text' => 'Silabus',
            'topik' => 'Topik',
            'jumlah_pg' => 0,
            'jumlah_essay' => 1,
            'level' => 'C2 - Memahami',
        ]);

        $this->assertCount(1, $result);
        $this->assertEquals('Soal dari SSE', $result[0]['teks']);
    }

    public function test_calculate_weights_distributes_exactly_to_max_score(): void
    {
        // 1. PG only: 100 points into 3 questions -> 34, 33, 33 (sum = 100)
        $weightsPg = AiQuestionService::calculateWeights(nilaiMaksimal: 100, jumlahPg: 3, jumlahEssay: 0);
        $this->assertCount(3, $weightsPg['pg']);
        $this->assertEmpty($weightsPg['essay']);
        $this->assertEquals(100, array_sum($weightsPg['pg']));

        // 2. Essay only: 100 points into 4 questions -> 25, 25, 25, 25 (sum = 100)
        $weightsEssay = AiQuestionService::calculateWeights(nilaiMaksimal: 100, jumlahPg: 0, jumlahEssay: 4);
        $this->assertEmpty($weightsEssay['pg']);
        $this->assertCount(4, $weightsEssay['essay']);
        $this->assertEquals(100, array_sum($weightsEssay['essay']));

        // 3. PG (10) and Essay (2) with 60% PG ratio:
        // PG total = 60 (10 questions * 6 points)
        // Essay total = 40 (2 questions * 20 points)
        // Total sum = 100
        $weightsCombined = AiQuestionService::calculateWeights(nilaiMaksimal: 100, jumlahPg: 10, jumlahEssay: 2, porsiPg: 60);
        $this->assertCount(10, $weightsCombined['pg']);
        $this->assertCount(2, $weightsCombined['essay']);
        $this->assertEquals(60, array_sum($weightsCombined['pg']));
        $this->assertEquals(40, array_sum($weightsCombined['essay']));
        $this->assertEquals(100, array_sum($weightsCombined['pg']) + array_sum($weightsCombined['essay']));
    }

    public function test_generate_questions_applies_exact_weights_from_nilai_maksimal(): void
    {
        $mockPayload = [
            'questions' => [
                ['teks' => 'PG 1', 'tipe' => 'pilihan_ganda', 'bobot' => 999, 'opsi' => [['teks' => 'A', 'is_correct' => true]]],
                ['teks' => 'PG 2', 'tipe' => 'pilihan_ganda', 'bobot' => 999, 'opsi' => [['teks' => 'A', 'is_correct' => true]]],
                ['teks' => 'Essay 1', 'tipe' => 'essay', 'bobot' => 999],
            ],
        ];

        Http::fake([
            'https://api.9router.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($mockPayload)]],
                ],
            ], 200),
        ]);

        $service = new AiQuestionService();
        $result = $service->generateQuestions([
            'model' => 'ag/gemini-3.8-flash-medium',
            'rps_text' => 'Silabus',
            'topik' => 'Topik',
            'jumlah_pg' => 2,
            'jumlah_essay' => 1,
            'nilai_maksimal' => 100,
            'porsi_pg' => 60,
        ]);

        // PG target = 60 for 2 items -> 30 each
        // Essay target = 40 for 1 item -> 40
        $this->assertEquals(30, $result[0]['bobot']);
        $this->assertEquals(30, $result[1]['bobot']);
        $this->assertEquals(40, $result[2]['bobot']);
        $this->assertEquals(100, $result[0]['bobot'] + $result[1]['bobot'] + $result[2]['bobot']);
    }
}
