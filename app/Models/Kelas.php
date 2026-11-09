<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    protected $table = 'kelas';

    protected $fillable = [
        'nama',
        'pin',
        'cover_path',
        'created_by',
    ];

    public function peserta(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kelas_user')->withTimestamps();
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
