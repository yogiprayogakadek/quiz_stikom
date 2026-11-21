<?php

namespace App\Jobs;

use App\Events\OpenCodeResultReady;
use App\Services\OpenCode\OpenCodeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessWithOpenCode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 420;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $resultKey,
        public array $input,
        public ?string $model = null,
        public ?string $agent = null,
    ) {
    }

    public function handle(OpenCodeClient $client): void
    {
        $result = $client->process($this->prompt(), $this->model, $this->agent);

        $payload = [
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now()->toIso8601String(),
        ];

        Cache::put($this->cacheKey(), $payload, now()->addDay());

        event(new OpenCodeResultReady($this->resultKey, $payload));
    }

    public function failed(Throwable $exception): void
    {
        Cache::put($this->cacheKey(), [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ], now()->addDay());
    }

    private function cacheKey(): string
    {
        return 'opencode:result:'.$this->resultKey;
    }

    private function prompt(): string
    {
        $parts = [];

        $categories = $this->input['categories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $parts[] = 'Kategori yang tersedia: '.implode(', ', $categories).'.';
        }

        $fields = $this->input['fields'] ?? [];
        if (is_array($fields) && $fields !== []) {
            $parts[] = 'Field wajib: '.implode(', ', $fields).'.';
        }

        $data = $this->input['data'] ?? '';
        $encoded = is_string($data)
            ? $data
            : (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parts[] = 'Data: '.$encoded;

        return implode("\n", $parts)."\n";
    }
}
