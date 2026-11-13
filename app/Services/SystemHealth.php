<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemHealth
{
    public function __construct(private DatabaseBackupService $backup) {}

    /**
     * @return array{
     *     failed_jobs: int,
     *     failed_job_list: array<int, array{id: string, queue: string, failed_at: ?string, error: string}>,
     *     pending_jobs: int,
     *     last_backup: ?array{nama: string, ukuran: int, diubah: int},
     *     debug: bool,
     *     queue: string,
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

        return [
            'failed_jobs' => $failedJobs,
            'failed_job_list' => $failedJobList,
            'pending_jobs' => $queueDriver === 'database'
                ? DB::connection(config('queue.connections.database.connection') ?: config('database.default'))
                    ->table(config('queue.connections.database.table', 'jobs'))
                    ->count()
                : 0,
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
