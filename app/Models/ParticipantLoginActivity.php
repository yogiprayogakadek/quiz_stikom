<?php

namespace App\Models;

use App\Events\PesertaStatusLogin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantLoginActivity extends Model
{
    protected $fillable = [
        'user_id',
        'nama',
        'username',
        'status',
        'reason',
    ];

    public static function recordAndBroadcast(User $user, string $status, ?string $reason = null): self
    {
        $activity = static::create([
            'user_id' => $user->id,
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
