<?php

namespace App\Models;

use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'organization_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public static function record(
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $metadata = [],
        ?int $organizationId = null,
    ): self {
        return static::create([
            'organization_id' => $organizationId ?? app(OrganizationContext::class)->id(),
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => app()->bound('request') ? request()->ip() : null,
        ]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
