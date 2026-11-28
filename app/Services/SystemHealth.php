<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemHealth
{
    public const SCHEDULER_HEARTBEAT_FILE = 'scheduler-heartbeat.json';

    public const SCHEDULER_STALE_SECONDS = 180;

    public function __construct(private DatabaseBackupService $backup) {}

    public function recordSchedulerHeartbeat(): void
    {
        try {
            Storage::disk('local')->put(
                self::SCHEDULER_HEARTBEAT_FILE,
                (string) json_encode(['last_run_at' => now()->timestamp]),
            );
        } catch (\Throwable) {
            // Jangan gagalkan eksekusi perintah terjadwal bila disk sedang tidak dapat ditulis.
        }
    }

    /**
     * @return array{
     *     failed_jobs: int,
     *     failed_job_list: array<int, array{id: string, queue: string, failed_at: ?string, error: string}>,
     *     pending_jobs: int,
     *     last_backup: ?array{nama: string, ukuran: int, diubah: int},
     *     debug: bool,
     *     queue: string,
     *     queue_sizes: array{realtime: int, default: int},
     *     queue_oldest_age_seconds: array{realtime: ?int, default: ?int},
     *     redis: array{configured: bool, available: bool, latency_ms: ?float, error: ?string},
     *     database_latency_ms: ?float,
     *     reverb: array{available: bool, latency_ms: ?float, host: string, port: int},
     *     scheduler: array{active: bool, last_run_at: ?int, age_seconds: ?int},
     *     mail_configured: bool,
     *     disk_free: ?int,
     *     disk_total: ?int,
     *     log_path: string,
     *     log_modified_at: ?int
     * }
     */
    public function snapshot(): array
    {
        $failedJobs = $this->failedJobsCount();
        $failedJobList = $this->failedJobs()
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get()
            ->map(fn ($job) => [
                'id' => (string) ($job->uuid ?: $job->id),
                'queue' => (string) $job->queue,
                'failed_at' => $job->failed_at,
                'error' => Str::limit(trim(Str::before((string) $job->exception, "\n")), 220),
            ])
            ->all();

        $logPath = storage_path('logs/laravel.log');
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $queueDriver = (string) config('queue.default');
        $queues = $this->queueHealth();

        return [
            'failed_jobs' => $failedJobs,
            'failed_job_list' => $failedJobList,
            'pending_jobs' => array_sum($queues['sizes']),
            'queue_sizes' => $queues['sizes'],
            'queue_oldest_age_seconds' => $queues['oldest'],
            'redis' => $this->redisHealth(),
            'database_latency_ms' => $this->databaseLatency(),
            'reverb' => $this->reverbHealth(),
            'scheduler' => $this->schedulerHealth(),
            'last_backup' => $this->backup->daftar()[0] ?? null,
            'debug' => (bool) config('app.debug'),
            'queue' => (string) config('queue.default'),
            'mail_configured' => filled(AppSetting::current()->mail_host),
            'disk_free' => is_float($free) ? (int) $free : null,
            'disk_total' => is_float($total) ? (int) $total : null,
            'log_path' => $logPath,
            'log_modified_at' => is_file($logPath) ? filemtime($logPath) : null,
        ];
    }

    /** @return array{sizes: array{realtime: int, default: int}, oldest: array{realtime: ?int, default: ?int}} */
    private function queueHealth(): array
    {
        $sizes = ['realtime' => 0, 'default' => 0];
        $oldest = ['realtime' => null, 'default' => null];

        try {
            $connection = Queue::connection();
            foreach (array_keys($sizes) as $name) {
                $sizes[$name] = (int) $connection->size($name);
                $createdAt = match (true) {
                    $connection instanceof RedisQueue => $connection->creationTimeOfOldestPendingJob($name),
                    $connection instanceof DatabaseQueue => $connection->getDatabase()
                        ->table(config('queue.connections.database.table', 'jobs'))
                        ->where('queue', $connection->getQueue($name))
                        ->min('created_at'),
                    default => null,
                };
                $oldest[$name] = $createdAt ? max(0, now()->timestamp - (int) $createdAt) : null;
            }
        } catch (\Throwable) {
            // Health screen must remain reachable while the queue backend is down.
        }

        return compact('sizes', 'oldest');
    }

    /** @return array{configured: bool, available: bool, latency_ms: ?float, error: ?string} */
    private function redisHealth(): array
    {
        $configured = config('queue.default') === 'redis'
            || config('cache.default') === 'redis'
            || config('session.driver') === 'redis';

        if (! $configured) {
            return ['configured' => false, 'available' => false, 'latency_ms' => null, 'error' => null];
        }

        $start = hrtime(true);
        try {
            Redis::connection('cache')->command('ping');

            return [
                'configured' => true,
                'available' => true,
                'latency_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
                'error' => null,
            ];
        } catch (\Throwable $error) {
            return [
                'configured' => true,
                'available' => false,
                'latency_ms' => null,
                'error' => Str::limit($error->getMessage(), 160),
            ];
        }
    }

    private function databaseLatency(): ?float
    {
        $start = hrtime(true);
        try {
            DB::select('select 1');

            return round((hrtime(true) - $start) / 1_000_000, 2);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{available: bool, latency_ms: ?float, host: string, port: int} */
    private function reverbHealth(): array
    {
        $server = (array) config('reverb.servers.'.config('reverb.default'), []);
        $host = (string) ($server['host'] ?? '127.0.0.1');
        $host = in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $host;
        $port = (int) ($server['port'] ?? 8080);
        $start = hrtime(true);
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 0.2);

        if (! is_resource($socket)) {
            return ['available' => false, 'latency_ms' => null, 'host' => $host, 'port' => $port];
        }

        fclose($socket);

        return [
            'available' => true,
            'latency_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            'host' => $host,
            'port' => $port,
        ];
    }

    /** @return array{active: bool, last_run_at: ?int, age_seconds: ?int} */
    private function schedulerHealth(): array
    {
        try {
            $disk = Storage::disk('local');
            if (! $disk->exists(self::SCHEDULER_HEARTBEAT_FILE)) {
                return ['active' => false, 'last_run_at' => null, 'age_seconds' => null];
            }

            $decoded = json_decode((string) $disk->get(self::SCHEDULER_HEARTBEAT_FILE), true);
            $lastRunAt = isset($decoded['last_run_at']) && is_numeric($decoded['last_run_at'])
                ? (int) $decoded['last_run_at']
                : null;

            if ($lastRunAt === null || $lastRunAt <= 0) {
                return ['active' => false, 'last_run_at' => null, 'age_seconds' => null];
            }

            $age = max(0, now()->timestamp - $lastRunAt);

            return [
                'active' => $age <= self::SCHEDULER_STALE_SECONDS,
                'last_run_at' => $lastRunAt,
                'age_seconds' => $age,
            ];
        } catch (\Throwable) {
            return ['active' => false, 'last_run_at' => null, 'age_seconds' => null];
        }
    }

    public function failedJobsCount(): int
    {
        return $this->failedJobs()->count();
    }

    private function failedJobs(): Builder
    {
        return DB::connection(config('queue.failed.database') ?: config('database.default'))
            ->table(config('queue.failed.table', 'failed_jobs'));
    }
}
