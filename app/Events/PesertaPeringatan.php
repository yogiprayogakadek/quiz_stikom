<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesertaPeringatan implements ShouldBroadcast
{
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $token,
        public array $payload,
    ) {}

    public static function safeDispatch(string $token, array $payload): void
    {
        try {
            static::dispatch($token, $payload);
        } catch (\Throwable $e) {
            Log::warning('PesertaPeringatan broadcast error: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('display.'.$this->token)];
    }

    public function broadcastAs(): string
    {
        return 'PesertaPeringatan';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
