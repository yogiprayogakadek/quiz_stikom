<?php

namespace App\Events\Concerns;

trait BroadcastsOnRealtimeQueue
{
    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
