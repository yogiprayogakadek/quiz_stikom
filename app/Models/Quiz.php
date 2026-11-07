<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'kelas_id',
        'judul',
        'jumlah_soal',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kelas_id' => 'integer',
            'jumlah_soal' => 'integer',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
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
