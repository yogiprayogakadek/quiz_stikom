<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

class OrganizationContext
{
    public function current(): ?Organization
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            $requestedId = session('organization_id');

            return $requestedId
                ? Organization::query()->whereKey($requestedId)->where('is_active', true)->first()
                : null;
        }

        $requestedId = session('organization_id');
        $query = $user->organizations()->wherePivot('status', 'active')->where('organizations.is_active', true);

        return $requestedId
            ? (clone $query)->whereKey($requestedId)->first()
            : $query->orderBy('organizations.id')->first();
    }

    public function id(): ?int
    {
        return $this->current()?->id;
    }

    public function set(Organization $organization): void
    {
        session(['organization_id' => $organization->id]);
    }

    public function timezone(): string
    {
        return $this->current()?->timezone ?? config('app.timezone');
    }

    public function applyLocale(?Organization $organization = null): void
    {
        $organization ??= $this->current();
        $locale = $organization === null
            ? config('app.locale')
            : (OrganizationSetting::effective($organization->id)->locale ?: config('app.locale'));

        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }
}
