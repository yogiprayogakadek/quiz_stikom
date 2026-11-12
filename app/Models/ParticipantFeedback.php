<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantFeedback extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'participant_feedbacks';

    protected $fillable = [
        'attempt_id',
        'organization_id',
        'user_id',
        'status',
        'rating',
        'message',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
