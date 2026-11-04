<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'judul',
        'jumlah_soal',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_soal' => 'integer',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_questions');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_quiz')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function soalTerpilih(): int
    {
        $total = $this->questions()->count();

        return $this->jumlah_soal === null ? $total : min($this->jumlah_soal, $total);
    }
}
