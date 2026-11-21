<?php

namespace Tests\Unit;

use App\Models\Answer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerAiSuggestionsAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_suggestions_json_is_cast_to_array(): void
    {
        $answer = new Answer();
        $answer->attempt_id = 1;
        $answer->question_id = 1;
        $answer->answered_at = now();
        $answer->ai_suggestions_json = [
            'ketat' => ['score' => 12, 'feedback' => 'Kurang detail.'],
            'seimbang' => ['score' => 15, 'feedback' => 'Cukup.'],
            'longgar' => ['score' => 18, 'feedback' => 'Bagus.'],
        ];

        // Should be fillable and cast properly on saving & retrieval
        $this->assertContains('ai_suggestions_json', $answer->getFillable());
        $this->assertSame('array', $answer->getCasts()['ai_suggestions_json'] ?? null);
    }
}
