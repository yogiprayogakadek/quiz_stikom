<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionMessage extends Model
{
    protected $fillable = [
        'quiz_session_id',
        'attempt_id',
        'user_id',
        'pengirim_id',
        'teks',
        'tipe',
        'ditutup_at',
    ];

    protected function casts(): array
    {
        return [
            'ditutup_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengirim_id');
    }

    /**
     * @return Collection<int, self>
     */
    public static function aktifUntukAttempt(int $attemptId): Collection
    {
        return static::query()
            ->where('attempt_id', $attemptId)
            ->whereNull('ditutup_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{id: int, teks: string, tipe: string, waktu: string}
     */
    public function untukPeserta(): array
    {
        return [
            'id' => $this->id,
            'teks' => $this->teks,
            'tipe' => $this->tipe,
            'waktu' => $this->created_at?->timezone(config('app.timezone'))->format('H:i') ?? '',
        ];
    }
}
