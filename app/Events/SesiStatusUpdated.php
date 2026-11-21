<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsOnRealtimeQueue;
use App\Models\QuizSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SesiStatusUpdated implements ShouldBroadcast
{
    use BroadcastsOnRealtimeQueue, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QuizSession $session,
        public array $extra = [],
    ) {}

    public static function safeDispatch(QuizSession $session, array $extra = []): void
    {
        try {
            static::dispatch($session, $extra);
        } catch (\Throwable $e) {
            Log::warning('SesiStatusUpdated broadcast error: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('quiz-sessions')];
    }

    public function broadcastAs(): string
    {
        return 'SesiStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return array_merge([
            'session_id' => $this->session->id,
            'status' => $this->session->status,
            'mode' => $this->session->mode,
            'nama' => $this->session->nama,
            'timestamp' => now()->timestamp,
        ], $this->extra);
    }
}
