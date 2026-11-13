<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParticipantLinking
{
    public function findExisting(string $username): ?User
    {
        return User::withTrashed()->where('username', $username)->first();
    }

    public function namesMatch(User $participant, string $name): bool
    {
        return Str::lower(Str::squish($participant->nama)) === Str::lower(Str::squish($name));
    }

    public function isActiveMember(User $participant, int $organizationId): bool
    {
        return $participant->organizations()
            ->whereKey($organizationId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function link(User $participant, Organization $organization, string $source, array $metadata = []): void
    {
        DB::transaction(function () use ($participant, $organization, $source, $metadata) {
            $participant->organizations()->syncWithoutDetaching([
                $organization->id => ['role' => 'participant', 'status' => 'active'],
            ]);

            AuditLog::record('participant_linked', 'user', $participant->id, array_merge([
                'name' => $participant->nama,
                'username' => $participant->username,
                'source' => $source,
            ], $metadata), $organization->id);
        });
    }

    public function maskedEmail(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 2).'***@'.$domain;
    }
}
