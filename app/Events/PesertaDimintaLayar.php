<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesertaDimintaLayar implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
        public int $attemptId,
        public int $screenPeekUntil,
    ) {}

    public static function safeDispatch(int $sessionId, int $userId, int $attemptId, int $screenPeekUntil): void
    {
        try {
            event(new self($sessionId, $userId, $attemptId, $screenPeekUntil));
        } catch (\Throwable $e) {
            Log::warning('Broadcast PesertaDimintaLayar gagal: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel("session-user.{$this->sessionId}.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'PesertaDimintaLayar';
    }

    public function broadcastWith(): array
    {
        return [
            'attempt_id' => $this->attemptId,
            'screen_peek_until' => $this->screenPeekUntil,
            'server_now' => now()->getTimestampMs(),
        ];
    }
}
