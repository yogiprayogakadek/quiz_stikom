<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\AuditLog;
use RuntimeException;

class EssayGrader
{
    public function __construct(
        private AiQuestionService $ai,
        private OrganizationUsage $usage,
    ) {}

    /**
     * @return array{score: int, confidence: int, feedback: string, model: string, provider: string}
     */
    public function suggest(Answer $answer): array
    {
        $answer->loadMissing(['question', 'attempt']);

        $question = $answer->question;
        $attempt = $answer->attempt;

        if ($question === null || ! $question->isEssay()) {
            throw new RuntimeException('Jawaban ini bukan soal essay.');
        }

        if (blank($answer->jawaban_teks)) {
            throw new RuntimeException('Jawaban essay kosong.');
        }

        $organizationId = $attempt?->organization_id;

        if ($organizationId !== null) {
            $this->usage->assertAiQuota($organizationId);
        }

        $suggestion = $this->ai->evaluateEssay(
            $question->teks,
            $question->kunci_essay,
            $question->rubrik_essay ?? [],
            (string) $answer->jawaban_teks,
            (int) $question->bobot,
        );

        if ($organizationId !== null) {
            $this->usage->recordAi($organizationId, 'essay');
        }

        $answer->update([
            'ai_suggested_score' => $suggestion['score'],
            'ai_confidence' => $suggestion['confidence'],
            'ai_feedback' => $suggestion['feedback'],
            'ai_model' => $suggestion['model'],
            'ai_reviewed_at' => now(),
        ]);

        AuditLog::record('essay_ai_suggested', 'answer', $answer->id, [
            'attempt_id' => $answer->attempt_id,
            'question_id' => $answer->question_id,
            'score' => $suggestion['score'],
            'confidence' => $suggestion['confidence'],
            'model' => $suggestion['model'],
            'provider' => $suggestion['provider'],
        ], $organizationId);

        return $suggestion;
    }

    public function applyFinal(
        Answer $answer,
        int $score,
        ?int $userId = null,
        bool $auto = false,
        bool $onlyIfUnscored = false,
    ): bool {
        $answer->loadMissing(['question', 'attempt']);

        $question = $answer->question;

        if ($question === null || ! $question->isEssay()) {
            throw new RuntimeException('Jawaban ini bukan soal essay.');
        }

        $score = max(0, min((int) $question->bobot, $score));
        $previousScore = $answer->dinilai_at ? $answer->skor : null;

        $updated = Answer::query()
            ->whereKey($answer->id)
            ->when($onlyIfUnscored, fn ($query) => $query->whereNull('dinilai_at'))
            ->update([
                'skor' => $score,
                'dinilai_at' => now(),
                'dinilai_oleh' => $userId,
            ]);

        if ($updated === 0) {
            return false;
        }

        $aiSuggestion = AuditLog::query()
            ->where('subject_type', 'answer')
            ->where('subject_id', $answer->id)
            ->where('action', 'essay_ai_suggested')
            ->latest()
            ->first();

        AuditLog::record('essay_score_changed', 'answer', $answer->id, [
            'attempt_id' => $answer->attempt_id,
            'question_id' => $answer->question_id,
            'from' => $previousScore,
            'to' => $score,
            'ai_suggested_score' => $answer->ai_suggested_score,
            'ai_confidence' => $answer->ai_confidence,
            'ai_model' => $answer->ai_model,
            'ai_provider' => $aiSuggestion?->metadata['provider'] ?? null,
            'auto' => $auto,
        ], $answer->attempt?->organization_id);

        return true;
    }
}
