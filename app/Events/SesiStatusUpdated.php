<?php

namespace App\Events;

use App\Models\QuizSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SesiStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QuizSession $session,
        public array $extra = [],
    ) {}

    public static function safeDispatch(QuizSession $session, array $extra = []): void
    {
        try {
            static::dispatch($session, $extra);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SesiStatusUpdated broadcast error: ' . $e->getMessage());
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
