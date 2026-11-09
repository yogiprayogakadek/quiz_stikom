<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'status',
        'params',
        'questions',
        'error',
        'total',
        'done',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'questions' => 'array',
            'total' => 'integer',
            'done' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function selesai(): bool
    {
        return $this->status === 'selesai';
    }
}
