<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'timezone',
        'is_active',
        'ai_monthly_requests',
        'storage_mb',
        'admin_limit',
        'participant_limit',
        'class_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ai_monthly_requests' => 'integer',
            'storage_mb' => 'integer',
            'admin_limit' => 'integer',
            'participant_limit' => 'integer',
            'class_limit' => 'integer',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }
}
