<?php

namespace Tests\Unit;

use App\Services\AiQuestionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAuditAndEvaluateServiceTest extends TestCase
{
    public function test_audit_and_evaluate_essay_parses_detection_and_3_modes(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'ai_detection' => [
                                'score' => 78,
                                'reason' => 'Pola kalimat sangat terstruktur khas generator AI.',
                            ],
                            'suggestions' => [
                                'ketat' => ['score' => 14, 'feedback' => 'Implementasi kurang mendalam.'],
                                'seimbang' => ['score' => 17, 'confidence' => 85, 'feedback' => 'Konsep utama benar.'],
                                'longgar' => ['score' => 20, 'feedback' => 'Ide dasar dapat diterima.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $service = app(AiQuestionService::class);
        $result = $service->auditAndEvaluateEssay('Apa itu OOP?', 'OOP adalah...', [], 'OOP adalah konsep...', 20);

        $this->assertSame(78, $result['ai_detection']['score']);
        $this->assertSame('Pola kalimat sangat terstruktur khas generator AI.', $result['ai_detection']['reason']);
        $this->assertSame(14, $result['suggestions']['ketat']['score']);
        $this->assertSame('Implementasi kurang mendalam.', $result['suggestions']['ketat']['feedback']);
        $this->assertSame(17, $result['suggestions']['seimbang']['score']);
        $this->assertSame(85, $result['suggestions']['seimbang']['confidence']);
        $this->assertSame('Konsep utama benar.', $result['suggestions']['seimbang']['feedback']);
        $this->assertSame(20, $result['suggestions']['longgar']['score']);
        $this->assertSame('Ide dasar dapat diterima.', $result['suggestions']['longgar']['feedback']);
        $this->assertNotEmpty($result['model']);
        $this->assertNotEmpty($result['provider']);
    }
}
