<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'option_id',
        'jawaban_teks',
        'jawaban_parts_json',
        'waktu_jawab_ms',
        'skor',
        'ai_suggested_score',
        'ai_confidence',
        'ai_feedback',
        'ai_model',
        'ai_reviewed_at',
        'dinilai_at',
        'dinilai_oleh',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'jawaban_parts_json' => 'array',
            'waktu_jawab_ms' => 'integer',
            'skor' => 'integer',
            'ai_suggested_score' => 'integer',
            'ai_confidence' => 'integer',
            'ai_reviewed_at' => 'datetime',
            'dinilai_at' => 'datetime',
            'dinilai_oleh' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
