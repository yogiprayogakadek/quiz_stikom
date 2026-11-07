<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesertaDikeluarkan implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
        public string $pesan = 'Kamu telah dikeluarkan dari sesi ujian oleh pengajar.',
    ) {}

    public static function safeDispatch(int $sessionId, int $userId, string $pesan = 'Kamu telah dikeluarkan dari sesi ujian oleh pengajar.'): void
    {
        try {
            static::dispatch($sessionId, $userId, $pesan);
        } catch (\Throwable $e) {
            Log::warning('PesertaDikeluarkan broadcast error: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('session-user.' . $this->sessionId . '.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'PesertaDikeluarkan';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'pesan' => $this->pesan,
            'timestamp' => now()->timestamp,
        ];
    }
}
