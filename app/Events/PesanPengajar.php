<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesanPengajar implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
        public array $payload,
    ) {}

    public static function safeDispatch(int $sessionId, int $userId, array $payload): void
    {
        try {
            static::dispatch($sessionId, $userId, $payload);
        } catch (\Throwable $e) {
            Log::warning('PesanPengajar broadcast error: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('session-user.'.$this->sessionId.'.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'PesanPengajar';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
