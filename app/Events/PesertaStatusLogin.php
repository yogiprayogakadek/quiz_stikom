<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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
    ) {}

    public static function safeDispatch(
        int $userId,
        string $nama,
        string $username,
        string $status,
        ?string $waktu = null,
    ): void {
        try {
            static::dispatch(
                $userId,
                $nama,
                $username,
                $status,
                $waktu ?? now()->toISOString()
            );
        } catch (\Throwable $e) {
            Log::warning('PesertaStatusLogin broadcast error: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
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
            'timestamp' => now()->timestamp,
        ];
    }
}
