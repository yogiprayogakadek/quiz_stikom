<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SessionMonitorUpdated implements ShouldBroadcast
{
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public array $payload = [],
    ) {}

    public static function safeDispatch(int $sessionId, array $payload = []): void
    {
        try {
            static::dispatch($sessionId, $payload);
        } catch (\Throwable $e) {
            Log::warning('SessionMonitorUpdated broadcast error: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('session-monitor.'.$this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'SessionMonitorUpdated';
    }

    public function broadcastWith(): array
    {
        return array_merge([
            'session_id' => $this->sessionId,
            'timestamp' => now()->timestamp,
        ], $this->payload);
    }
}
