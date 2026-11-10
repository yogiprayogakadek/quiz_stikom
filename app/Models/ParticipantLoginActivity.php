<?php

namespace App\Models;

use App\Events\PesertaStatusLogin;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantLoginActivity extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'organization_id',
        'nama',
        'username',
        'status',
        'reason',
    ];

    public static function recordAndBroadcast(User $user, string $status, ?string $reason = null): self
    {
        $activity = static::create([
            'user_id' => $user->id,
            'organization_id' => app(OrganizationContext::class)->id(),
            'nama' => $user->nama,
            'username' => $user->username,
            'status' => $status,
            'reason' => $reason,
        ]);

        PesertaStatusLogin::safeDispatch(
            $user->id,
            $user->nama,
            $user->username,
            $status,
            $activity->created_at->toISOString(),
            $activity->id,
            $reason,
        );

        return $activity;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
