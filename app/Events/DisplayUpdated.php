<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisplayUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $token,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('display.'.$this->token)];
    }

    public function broadcastAs(): string
    {
        return 'DisplayUpdated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
