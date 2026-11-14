<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'urutan',
        'urutan_opsi_json',
        'ragu_ragu',
        'highlight_soal',
        'highlight_opsi_json',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'urutan_opsi_json' => 'array',
            'ragu_ragu' => 'boolean',
            'highlight_soal' => 'boolean',
            'highlight_opsi_json' => 'array',
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
}
