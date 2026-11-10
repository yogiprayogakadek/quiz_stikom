<?php

namespace App\Services;

use App\Models\AiUsageEvent;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrganizationUsage
{
    public function aiUsed(int $organizationId): int
    {
        $timezone = Organization::query()->whereKey($organizationId)->value('timezone') ?? config('app.timezone');
        $start = now($timezone)->startOfMonth()->utc();

        return (int) AiUsageEvent::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $start)
            ->sum('requests');
    }

    public function aiLimit(): int
    {
        return max(0, (int) config('platform.ai_monthly_requests', 1000));
    }

    public function assertAiQuota(int $organizationId, int $requests = 1): void
    {
        $requests = max(1, $requests);
        $used = $this->aiUsed($organizationId);
        $limit = $this->aiLimit();

        if ($used + $requests > $limit) {
            throw new RuntimeException("Kuota AI bulan ini tidak cukup. Terpakai {$used} dari {$limit} request. Hubungi operator platform untuk menaikkan batas.");
        }
    }

    public function recordAi(int $organizationId, string $kind, int $requests = 1): void
    {
        AiUsageEvent::create([
            'organization_id' => $organizationId,
            'kind' => $kind,
            'requests' => max(1, $requests),
        ]);
    }

    public function reserveAi(int $organizationId, string $kind, int $requests = 1): void
    {
        $requests = max(1, $requests);

        DB::transaction(function () use ($organizationId, $kind, $requests) {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();
            $this->assertAiQuota($organizationId, $requests);
            $this->recordAi($organizationId, $kind, $requests);
        });
    }

    public function storageUsed(int $organizationId): int
    {
        $paths = DB::table('organization_settings')
            ->where('organization_id', $organizationId)
            ->whereNotNull('logo_path')
            ->pluck('logo_path')
            ->merge(DB::table('kelas')
                ->where('organization_id', $organizationId)
                ->whereNotNull('cover_path')
                ->pluck('cover_path'))
            ->unique();

        $disk = Storage::disk('public');

        return (int) $paths->sum(fn (string $path) => $disk->exists($path) ? $disk->size($path) : 0);
    }

    public function storageLimitBytes(): int
    {
        return max(0, (int) config('platform.storage_mb', 1024)) * 1024 * 1024;
    }

    public function canStore(int $organizationId, int $bytes, ?string $replacing = null): bool
    {
        $used = $this->storageUsed($organizationId);

        if ($replacing !== null) {
            $disk = Storage::disk('public');
            $used -= $disk->exists($replacing) ? $disk->size($replacing) : 0;
        }

        return $used + max(0, $bytes) <= $this->storageLimitBytes();
    }

    /**
     * @return array{ai_used: int, ai_limit: int, ai_percent: int, storage_used: int, storage_limit: int, storage_percent: int}
     */
    public function summary(int $organizationId): array
    {
        $aiUsed = $this->aiUsed($organizationId);
        $aiLimit = $this->aiLimit();
        $storageUsed = $this->storageUsed($organizationId);
        $storageLimit = $this->storageLimitBytes();

        return [
            'ai_used' => $aiUsed,
            'ai_limit' => $aiLimit,
            'ai_percent' => $aiLimit > 0 ? min(100, (int) round($aiUsed / $aiLimit * 100)) : 100,
            'storage_used' => $storageUsed,
            'storage_limit' => $storageLimit,
            'storage_percent' => $storageLimit > 0 ? min(100, (int) round($storageUsed / $storageLimit * 100)) : 100,
        ];
    }
}
