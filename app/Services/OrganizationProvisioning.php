<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

class OrganizationProvisioning
{
    public function createForOwner(array $data, User $owner, array $auditMetadata = []): Organization
    {
        $organization = Organization::create([
            'name' => $data['name'],
            'slug' => strtolower($data['slug']),
            'type' => $data['type'],
            'timezone' => $data['timezone'],
            'is_active' => true,
        ]);

        $organization->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        AuditLog::record('organization_created', 'organization', $organization->id, array_merge([
            'name' => $organization->name,
            'owner_id' => $owner->id,
            'owner_username' => $owner->username,
        ], $auditMetadata), $organization->id);

        return $organization;
    }
}
