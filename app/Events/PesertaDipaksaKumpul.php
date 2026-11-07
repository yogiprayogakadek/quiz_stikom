<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesertaDipaksaKumpul implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
        public int $attemptId,
        public string $pesan = 'Pekerjaanmu telah dikumpulkan oleh pengajar.',
    ) {}

    public static function safeDispatch(int $sessionId, int $userId, int $attemptId, string $pesan = 'Pekerjaanmu telah dikumpulkan oleh pengajar.'): void
    {
        try {
            static::dispatch($sessionId, $userId, $attemptId, $pesan);
        } catch (\Throwable $e) {
            Log::warning('PesertaDipaksaKumpul broadcast error: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('session-user.' . $this->sessionId . '.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'PesertaDipaksaKumpul';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'attempt_id' => $this->attemptId,
            'pesan' => $this->pesan,
            'timestamp' => now()->timestamp,
        ];
    }
}
