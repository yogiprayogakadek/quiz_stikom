<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptSnapshot extends Model
{
    use BelongsToOrganization;

    public const MAX_SCREEN_PER_ATTEMPT = 40;

    public const MAX_WEBCAM_PER_ATTEMPT = 30;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'attempt_id',
        'question_urutan',
        'capture_source',
        'trigger_type',
        'path',
        'size_bytes',
        'luminance',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'question_urutan' => 'integer',
            'size_bytes' => 'integer',
            'luminance' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function url(): string
    {
        return route('admin.snapshots.show', $this);
    }
}
