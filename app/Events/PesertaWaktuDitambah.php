<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesertaWaktuDitambah implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
        public int $attemptId,
        public int $batasAkhirAt,
        public int $minutes,
        public int $serverNow,
    ) {}

    public static function safeDispatch(
        int $sessionId,
        int $userId,
        int $attemptId,
        int $batasAkhirAt,
        int $minutes,
        int $serverNow,
    ): void {
        try {
            static::dispatch($sessionId, $userId, $attemptId, $batasAkhirAt, $minutes, $serverNow);
        } catch (\Throwable $e) {
            Log::warning('PesertaWaktuDitambah broadcast error: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('session-user.'.$this->sessionId.'.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'PesertaWaktuDitambah';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'attempt_id' => $this->attemptId,
            'batas_akhir_at' => $this->batasAkhirAt,
            'minutes' => $this->minutes,
            'server_now' => $this->serverNow,
        ];
    }
}
