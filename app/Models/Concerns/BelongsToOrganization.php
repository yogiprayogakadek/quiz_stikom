<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            $organizationId = app(OrganizationContext::class)->id();
            if ($organizationId !== null) {
                $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
            }
        });

        static::creating(function ($model) {
            $organizationId = app(OrganizationContext::class)->id();
            if ($organizationId === null && Schema::hasTable('organizations') && Organization::query()->count() === 1) {
                $organizationId = Organization::query()->value('id');
            }
            $model->organization_id ??= $organizationId;
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
