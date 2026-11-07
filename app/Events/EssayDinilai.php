<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EssayDinilai implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
        public int $attemptId,
        public int $questionId,
        public int $skor,
    ) {}

    public static function safeDispatch(int $sessionId, int $userId, int $attemptId, int $questionId, int $skor): void
    {
        try {
            static::dispatch($sessionId, $userId, $attemptId, $questionId, $skor);
        } catch (\Throwable $e) {
            Log::warning('EssayDinilai broadcast error: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('session-user.' . $this->sessionId . '.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'EssayDinilai';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'attempt_id' => $this->attemptId,
            'question_id' => $this->questionId,
            'skor' => $this->skor,
            'timestamp' => now()->timestamp,
        ];
    }
}
