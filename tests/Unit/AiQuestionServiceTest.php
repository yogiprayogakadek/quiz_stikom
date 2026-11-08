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
}
