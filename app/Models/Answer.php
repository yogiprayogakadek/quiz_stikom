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
        'waktu_jawab_ms',
        'skor',
        'dinilai_at',
        'dinilai_oleh',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'waktu_jawab_ms' => 'integer',
            'skor' => 'integer',
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
