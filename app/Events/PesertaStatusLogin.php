<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PesertaStatusLogin implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $nama,
        public string $username,
        public string $status, // 'login' | 'logout'
        public string $waktu,
        public ?int $activityId = null,
        public ?string $reason = null,
    ) {}

    public static function safeDispatch(
        int $userId,
        string $nama,
        string $username,
        string $status,
        ?string $waktu = null,
        ?int $activityId = null,
        ?string $reason = null,
    ): void {
        try {
            static::dispatch(
                $userId,
                $nama,
                $username,
                $status,
                $waktu ?? now()->toISOString(),
                $activityId,
                $reason,
            );
        } catch (\Throwable $e) {
            Log::warning('PesertaStatusLogin broadcast error: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'PesertaStatusLogin';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'nama' => $this->nama,
            'username' => $this->username,
            'status' => $this->status,
            'waktu' => $this->waktu,
            'activity_id' => $this->activityId,
            'reason' => $this->reason,
            'timestamp' => now()->timestamp,
        ];
    }
}
