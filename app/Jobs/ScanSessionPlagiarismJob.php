<?php

namespace App\Jobs;

use App\Models\Answer;
use App\Models\QuizSession;
use App\Services\TextSimilarityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanSessionPlagiarismJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $sessionId) {}

    public function handle(TextSimilarityService $similarityService): void
    {
        $session = QuizSession::withoutGlobalScopes()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $answers = Answer::whereHas('attempt', function ($query) {
            $query->where('quiz_session_id', $this->sessionId);
        })
            ->whereHas('question', function ($query) {
                $query->where('tipe', 'essay');
            })
            ->with(['attempt.user', 'question'])
            ->get();

        if ($answers->isEmpty()) {
            return;
        }

        // ponytail: group answers per question, run local Jaccard shingling per question cluster
        $answersByQuestion = $answers->groupBy('question_id');

        foreach ($answersByQuestion as $questionId => $qAnswers) {
            $items = [];

            foreach ($qAnswers as $answer) {
                if (blank($answer->jawaban_teks)) {
                    continue;
                }

                $items[] = [
                    'id' => $answer->id,
                    'user_id' => $answer->attempt?->user_id,
                    'student_name' => $answer->attempt?->user?->nama ?? $answer->attempt?->user?->name ?? ('Peserta #'.$answer->id),
                    'text' => $answer->jawaban_teks,
                ];
            }

            if (count($items) <= 1) {
                foreach ($qAnswers as $answer) {
                    $answer->update([
                        'similarity_score' => null,
                        'similarity_data' => [],
                        'similarity_checked_at' => now(),
                    ]);
                }

                continue;
            }

            $matches = $similarityService->findMatches($items);

            foreach ($qAnswers as $answer) {
                if (! isset($matches[$answer->id])) {
                    $answer->update([
                        'similarity_score' => null,
                        'similarity_data' => [],
                        'similarity_checked_at' => now(),
                    ]);

                    continue;
                }

                $matchInfo = $matches[$answer->id];

                $answer->update([
                    'similarity_score' => $matchInfo['highest_score'],
                    'similarity_data' => $matchInfo['matches'],
                    'similarity_checked_at' => now(),
                ]);
            }
        }
    }
}
