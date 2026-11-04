<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_session_id',
        'status',
        'mulai_at',
        'batas_akhir_at',
        'selesai_at',
        'jumlah_benar',
        'skor_total',
    ];

    protected function casts(): array
    {
        return [
            'mulai_at' => 'integer',
            'batas_akhir_at' => 'integer',
            'selesai_at' => 'integer',
            'jumlah_benar' => 'integer',
            'skor_total' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(AttemptQuestion::class)->orderBy('urutan');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttemptEvent::class);
    }

    public function scopeBerjalan(Builder $query): Builder
    {
        return $query->where('status', 'berjalan');
    }

    public function isLewatBatas(?int $now = null): bool
    {
        if ($this->batas_akhir_at === null) {
            return false;
        }

        return ($now ?? now()->getTimestampMs()) > $this->batas_akhir_at;
    }

    public function waktuTersisaMs(?int $now = null): int
    {
        if ($this->batas_akhir_at === null) {
            return 0;
        }

        return max(0, $this->batas_akhir_at - ($now ?? now()->getTimestampMs()));
    }

    public function answeredQuestionIds(): array
    {
        return $this->answers()->pluck('question_id')->all();
    }
}
