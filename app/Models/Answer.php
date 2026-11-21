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
        'jawaban_data_json',
        'waktu_jawab_ms',
        'skor',
        'catatan_pengajar',
        'ai_suggested_score',
        'ai_confidence',
        'ai_feedback',
        'ai_model',
        'ai_reviewed_at',
        'similarity_score',
        'similarity_data',
        'similarity_checked_at',
        'ai_generated_score',
        'ai_generated_reason',
        'ai_generated_at',
        'dinilai_at',
        'dinilai_oleh',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'jawaban_parts_json' => 'array',
            'jawaban_data_json' => 'array',
            'waktu_jawab_ms' => 'integer',
            'skor' => 'integer',
            'ai_suggested_score' => 'integer',
            'ai_confidence' => 'integer',
            'similarity_score' => 'integer',
            'similarity_data' => 'array',
            'similarity_checked_at' => 'datetime',
            'ai_generated_score' => 'integer',
            'ai_generated_at' => 'datetime',
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
