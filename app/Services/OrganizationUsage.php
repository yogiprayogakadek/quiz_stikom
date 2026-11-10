<?php

namespace App\Services;

use App\Models\AiUsageEvent;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\OrganizationMembership;
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

    public function aiLimit(int $organizationId): int
    {
        return $this->limit($organizationId, 'ai_monthly_requests', (int) config('platform.ai_monthly_requests', 1000));
    }

    public function assertAiQuota(int $organizationId, int $requests = 1): void
    {
        $requests = max(1, $requests);
        $used = $this->aiUsed($organizationId);
        $limit = $this->aiLimit($organizationId);

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

    public function storageLimitBytes(int $organizationId): int
    {
        return $this->limit($organizationId, 'storage_mb', (int) config('platform.storage_mb', 1024)) * 1024 * 1024;
    }

    public function canStore(int $organizationId, int $bytes, ?string $replacing = null): bool
    {
        $used = $this->storageUsed($organizationId);

        if ($replacing !== null) {
            $disk = Storage::disk('public');
            $used -= $disk->exists($replacing) ? $disk->size($replacing) : 0;
        }

        return $used + max(0, $bytes) <= $this->storageLimitBytes($organizationId);
    }

    public function adminCount(int $organizationId): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->whereIn('role', ['owner', 'admin'])
            ->where('status', 'active')
            ->count();
    }

    public function participantCount(int $organizationId): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('role', 'participant')
            ->where('status', 'active')
            ->count();
    }

    public function classCount(int $organizationId): int
    {
        return Kelas::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count();
    }

    public function adminLimit(int $organizationId): int
    {
        return $this->limit($organizationId, 'admin_limit', (int) config('platform.admin_limit', 5));
    }

    public function participantLimit(int $organizationId): int
    {
        return $this->limit($organizationId, 'participant_limit', (int) config('platform.participant_limit', 1000));
    }

    public function classLimit(int $organizationId): int
    {
        return $this->limit($organizationId, 'class_limit', (int) config('platform.class_limit', 50));
    }

    public function canAddAdmin(int $organizationId): bool
    {
        return $this->adminCount($organizationId) < $this->adminLimit($organizationId);
    }

    public function canAddParticipant(int $organizationId): bool
    {
        return $this->participantCount($organizationId) < $this->participantLimit($organizationId);
    }

    public function canAddClass(int $organizationId): bool
    {
        return $this->classCount($organizationId) < $this->classLimit($organizationId);
    }

    public function participantRemaining(int $organizationId): int
    {
        return max(0, $this->participantLimit($organizationId) - $this->participantCount($organizationId));
    }

    /**
     * @return array{ai_used: int, ai_limit: int, ai_percent: int, storage_used: int, storage_limit: int, storage_percent: int, admin_used: int, admin_limit: int, participant_used: int, participant_limit: int, class_used: int, class_limit: int}
     */
    public function summary(int $organizationId): array
    {
        $aiUsed = $this->aiUsed($organizationId);
        $aiLimit = $this->aiLimit($organizationId);
        $storageUsed = $this->storageUsed($organizationId);
        $storageLimit = $this->storageLimitBytes($organizationId);

        return [
            'ai_used' => $aiUsed,
            'ai_limit' => $aiLimit,
            'ai_percent' => $aiLimit > 0 ? min(100, (int) round($aiUsed / $aiLimit * 100)) : 100,
            'storage_used' => $storageUsed,
            'storage_limit' => $storageLimit,
            'storage_percent' => $storageLimit > 0 ? min(100, (int) round($storageUsed / $storageLimit * 100)) : 100,
            'admin_used' => $this->adminCount($organizationId),
            'admin_limit' => $this->adminLimit($organizationId),
            'participant_used' => $this->participantCount($organizationId),
            'participant_limit' => $this->participantLimit($organizationId),
            'class_used' => $this->classCount($organizationId),
            'class_limit' => $this->classLimit($organizationId),
        ];
    }

    private function limit(int $organizationId, string $column, int $default): int
    {
        $value = Organization::query()->whereKey($organizationId)->value($column);

        return max(0, (int) ($value ?? $default));
    }
}
