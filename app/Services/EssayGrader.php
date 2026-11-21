<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\AuditLog;
use App\Support\QuestionContent;
use RuntimeException;

class EssayGrader
{
    public function __construct(
        private AiQuestionService $ai,
        private OrganizationUsage $usage,
    ) {}

    /**
     * @return array{score: int, confidence: int, feedback: string, model: string, provider: string, mode: string}
     */
    public function suggest(Answer $answer, string $mode = 'seimbang'): array
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

        $imageBlocks = collect($question->content_blocks_json ?? [])
            ->merge(collect($question->sub_soal_json ?? [])->flatMap(fn ($part) => $part['content_blocks'] ?? []))
            ->where('type', 'image');
        if ($imageBlocks->contains(fn ($block) => blank($block['ai_context'] ?? null))) {
            throw new RuntimeException('Tambahkan konteks gambar untuk AI sebelum meminta saran penilaian.');
        }

        $organizationId = $attempt?->organization_id;

        if ($organizationId !== null) {
            $this->usage->assertAiQuota($organizationId);
        }

        $suggestion = $this->ai->evaluateEssay(
            $question->teksUntukPenilaian(),
            $question->kunci_essay,
            $question->rubrik_essay ?? [],
            QuestionContent::answerForGrading($question, $answer),
            (int) $question->bobot,
            $mode,
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
            'mode' => $suggestion['mode'],
        ], $organizationId);

        return $suggestion;
    }

    /**
     * @return array{score: int, reason: string, model: string, provider: string}
     */
    public function detectAi(Answer $answer): array
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

        $result = $this->ai->detectAiGenerated(
            $question->teksUntukPenilaian(),
            QuestionContent::answerForGrading($question, $answer),
        );

        if ($organizationId !== null) {
            $this->usage->recordAi($organizationId, 'essay');
        }

        $answer->update([
            'ai_generated_score' => $result['score'],
            'ai_generated_reason' => $result['reason'],
            'ai_generated_at' => now(),
        ]);

        AuditLog::record('essay_ai_detected', 'answer', $answer->id, [
            'attempt_id' => $answer->attempt_id,
            'question_id' => $answer->question_id,
            'score' => $result['score'],
            'reason' => $result['reason'],
            'model' => $result['model'],
            'provider' => $result['provider'],
        ], $organizationId);

        return $result;
    }

    /**
     * @return array{
     *     ai_detection: array{score: int, reason: string},
     *     suggestions: array{
     *         ketat: array{score: int, feedback: string},
     *         seimbang: array{score: int, confidence: int, feedback: string},
     *         longgar: array{score: int, feedback: string}
     *     },
     *     model: string,
     *     provider: string
     * }
     */
    public function auditAndEvaluate(Answer $answer): array
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

        $imageBlocks = collect($question->content_blocks_json ?? [])
            ->merge(collect($question->sub_soal_json ?? [])->flatMap(fn ($part) => $part['content_blocks'] ?? []))
            ->where('type', 'image');
        if ($imageBlocks->contains(fn ($block) => blank($block['ai_context'] ?? null))) {
            throw new RuntimeException('Tambahkan konteks gambar untuk AI sebelum meminta saran penilaian.');
        }

        $organizationId = $attempt?->organization_id;

        if ($organizationId !== null) {
            $this->usage->assertAiQuota($organizationId);
        }

        $result = $this->ai->auditAndEvaluateEssay(
            $question->teksUntukPenilaian(),
            $question->kunci_essay,
            $question->rubrik_essay ?? [],
            QuestionContent::answerForGrading($question, $answer),
            (int) $question->bobot,
        );

        if ($organizationId !== null) {
            $this->usage->recordAi($organizationId, 'essay');
        }

        $seimbang = $result['suggestions']['seimbang'];

        $answer->update([
            'ai_generated_score' => $result['ai_detection']['score'],
            'ai_generated_reason' => $result['ai_detection']['reason'],
            'ai_generated_at' => now(),
            'ai_suggested_score' => $seimbang['score'],
            'ai_confidence' => $seimbang['confidence'] ?? 80,
            'ai_feedback' => $seimbang['feedback'],
            'ai_suggestions_json' => $result['suggestions'],
            'ai_model' => $result['model'],
            'ai_reviewed_at' => now(),
        ]);

        AuditLog::record('essay_audit_and_evaluated', 'answer', $answer->id, [
            'attempt_id' => $answer->attempt_id,
            'question_id' => $answer->question_id,
            'ai_detection_score' => $result['ai_detection']['score'],
            'suggested_scores' => [
                'ketat' => $result['suggestions']['ketat']['score'],
                'seimbang' => $seimbang['score'],
                'longgar' => $result['suggestions']['longgar']['score'],
            ],
            'model' => $result['model'],
            'provider' => $result['provider'],
        ], $organizationId);

        return $result;
    }

    public function applyFinal(
        Answer $answer,
        int $score,
        ?int $userId = null,
        bool $auto = false,
        bool $onlyIfUnscored = false,
        ?string $catatan = null,
    ): bool {
        $answer->loadMissing(['question', 'attempt']);

        $question = $answer->question;

        if ($question === null || ! $question->isEssay()) {
            throw new RuntimeException('Jawaban ini bukan soal essay.');
        }

        $score = max(0, min((int) $question->bobot, $score));
        $previousScore = $answer->dinilai_at ? $answer->skor : null;
        $nilaiTetap = $answer->dinilai_at !== null && (int) $previousScore === $score;

        // Catatan saja pada jawaban yang sudah dinilai tidak boleh menyentuh
        // nilai, waktu penilaian, atau menambah log perubahan nilai.
        $perubahan = $nilaiTetap ? [] : [
            'skor' => $score,
            'dinilai_at' => now(),
            'dinilai_oleh' => $userId,
        ];

        if ($catatan !== null) {
            $perubahan['catatan_pengajar'] = trim($catatan) === '' ? null : trim($catatan);
        }

        if ($perubahan === []) {
            return ! $onlyIfUnscored;
        }

        $updated = Answer::query()
            ->whereKey($answer->id)
            ->when($onlyIfUnscored, fn ($query) => $query->whereNull('dinilai_at'))
            ->update($perubahan);

        if ($updated === 0) {
            return false;
        }

        if ($nilaiTetap) {
            return true;
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
            'ai_mode' => $aiSuggestion?->metadata['mode'] ?? null,
            'auto' => $auto,
        ], $answer->attempt?->organization_id);

        return true;
    }
}
