<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\QuizSession;

class NilaiService
{
    public function attempt(Attempt $attempt): int
    {
        if ($attempt->nilai !== null) {
            return max(0, min(100, (int) $attempt->nilai));
        }

        $attempt->loadMissing('quizSession');

        if ($attempt->quizSession?->mode === 'acak') {
            return max(0, min(100, (int) $attempt->skor_total));
        }

        $total = max(1, $attempt->quizSession?->sessionQuestions()->count() ?? 1);

        return max(0, min(100, (int) round($attempt->skor_total / ($total * 1000) * 100)));
    }

    public function untukSesi(QuizSession $session, int $userId): ?int
    {
        $attempt = $session->attempts()
            ->where('user_id', $userId)
            ->where('status', 'selesai')
            ->first();

        return $attempt === null ? null : $this->attempt($attempt);
    }

    public function terbaikPerKuis(int $quizId, int $userId): int
    {
        $attempts = Attempt::query()
            ->where('user_id', $userId)
            ->where('status', 'selesai')
            ->whereHas('quizSession', fn ($q) => $q->where('quiz_id', $quizId))
            ->get();

        return (int) $attempts->map(fn (Attempt $a) => $this->attempt($a))->max();
    }
}
