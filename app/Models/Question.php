<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teks',
        'durasi_detik',
        'pembahasan',
    ];

    protected function casts(): array
    {
        return [
            'durasi_detik' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    public function correctOption(): ?Option
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_questions');
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('session_questions')
                ->join('quiz_sessions', 'quiz_sessions.id', '=', 'session_questions.quiz_session_id')
                ->whereColumn('session_questions.question_id', 'questions.id')
                ->whereIn('quiz_sessions.status', ['lobby', 'berjalan', 'selesai']);
        })->whereDoesntHave('quizzes.sessions', fn (Builder $q) => $q->whereIn('status', ['lobby', 'berjalan', 'selesai']));
    }

    public function isLocked(): bool
    {
        $sudahDibuka = fn (Builder $q) => $q->whereIn('status', ['lobby', 'berjalan', 'selesai']);

        return SessionQuestion::query()
            ->where('question_id', $this->id)
            ->whereHas('quizSession', $sudahDibuka)
            ->exists()
            || $this->quizzes()->whereHas('sessions', $sudahDibuka)->exists();
    }
}
