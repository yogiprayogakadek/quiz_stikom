<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationOwnership
{
    public function transfer(Organization $organization, User $newOwner, bool $removePreviousOwners): void
    {
        DB::transaction(function () use ($organization, $newOwner, $removePreviousOwners) {
            $locked = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentOwners = $locked->owners()->lockForUpdate()->get();

            if ($newOwner->trashed() || $newOwner->role !== 'admin') {
                throw ValidationException::withMessages([
                    'new_owner_id' => 'Owner baru harus akun admin yang aktif.',
                ]);
            }

            if ($currentOwners->contains('id', $newOwner->id) && $currentOwners->count() === 1) {
                throw ValidationException::withMessages([
                    'new_owner_id' => 'Pilih owner lain untuk memindahkan kepemilikan.',
                ]);
            }

            $previousOwners = $currentOwners->reject(fn (User $owner) => $owner->is($newOwner));

            $locked->users()->syncWithoutDetaching([
                $newOwner->id => ['role' => 'owner', 'status' => 'active'],
            ]);

            foreach ($previousOwners as $previousOwner) {
                if ($removePreviousOwners) {
                    $locked->users()->detach($previousOwner->id);
                } else {
                    $locked->users()->updateExistingPivot($previousOwner->id, [
                        'role' => 'admin',
                        'status' => 'active',
                    ]);
                }
            }

            AuditLog::record('ownership_transferred', 'organization', $locked->id, [
                'previous_owner_ids' => $previousOwners->pluck('id')->values()->all(),
                'new_owner_id' => $newOwner->id,
                'previous_owner_action' => $removePreviousOwners ? 'remove' : 'keep_admin',
            ], $locked->id);
        });

        $organization->refresh();
    }
}
