<?php

namespace App\Jobs;

use App\Events\EssayDinilai;
use App\Events\SessionMonitorUpdated;
use App\Models\Attempt;
use App\Services\EssayGrader;
use App\Services\NilaiService;
use App\Services\QuizEngine;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AutoGradeEssayJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $attemptId) {}

    public function handle(QuizEngine $engine): void
    {
        $attempt = Attempt::withoutGlobalScopes()
            ->with('quizSession.quiz')
            ->find($this->attemptId);

        if ($attempt === null
            || $attempt->status !== 'selesai'
            || $attempt->quizSession?->mode !== 'acak'
            || ! $attempt->quizSession?->quiz?->auto_grade_essay) {
            return;
        }

        $answers = $attempt->answers()
            ->whereHas('question', fn ($query) => $query->where('tipe', 'essay'))
            ->with('question')
            ->get()
            ->filter(fn ($answer) => filled($answer->jawaban_teks) && $answer->dinilai_at === null);

        if ($answers->isEmpty()) {
            return;
        }

        $context = app(OrganizationContext::class);
        $context->force($attempt->organization);

        try {
            $grader = app(EssayGrader::class);
            $dinilai = [];

            foreach ($answers as $answer) {
                try {
                    $suggestion = $answer->ai_reviewed_at !== null && $answer->ai_suggested_score !== null
                        ? [
                            'score' => $answer->ai_suggested_score,
                            'confidence' => $answer->ai_confidence,
                            'feedback' => $answer->ai_feedback,
                            'model' => $answer->ai_model,
                        ]
                        : $grader->suggest($answer);

                    if (! $grader->applyFinal($answer, (int) $suggestion['score'], null, true, true)) {
                        continue;
                    }

                    $dinilai[] = [
                        'question_id' => $answer->question_id,
                        'score' => (int) $suggestion['score'],
                    ];
                } catch (RuntimeException $e) {
                    Log::warning('Auto-grade essay dilewati: '.$e->getMessage(), [
                        'attempt_id' => $attempt->id,
                        'answer_id' => $answer->id,
                    ]);

                    if (str_contains($e->getMessage(), 'Kuota AI')) {
                        break;
                    }
                } catch (Throwable $e) {
                    Log::warning('Auto-grade essay gagal: '.$e->getMessage(), [
                        'attempt_id' => $attempt->id,
                        'answer_id' => $answer->id,
                    ]);
                }
            }

            $engine->hitungSkorAcak($attempt);
            app(NilaiService::class)->terapkanUntukSesi($attempt->quizSession);

            foreach ($dinilai as $item) {
                EssayDinilai::safeDispatch(
                    $attempt->quiz_session_id,
                    $attempt->user_id,
                    $attempt->id,
                    $item['question_id'],
                    $item['score'],
                );
            }

            SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, [
                'action' => 'essay_graded',
                'attempt_id' => $attempt->id,
            ]);
        } finally {
            $context->force(null);
        }
    }
}
