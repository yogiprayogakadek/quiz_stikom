<?php

namespace App\Jobs;

use App\Models\QuizSession;
use App\Services\QuizEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NextQuestionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $sessionId,
        public int $urutan,
    ) {}

    public function handle(QuizEngine $engine): void
    {
        $session = QuizSession::find($this->sessionId);

        if ($session === null) {
            return;
        }

        $engine->lanjutSoal($session, $this->urutan);
    }
}
