<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'quiz_id',
        'organization_id',
        'kelas_id',
        'nama',
        'mode',
        'jenis',
        'remidi_dari_session_id',
        'remidi_soal',
        'acak_opsi',
        'tampilkan_pembahasan',
        'pembahasan_mode',
        'leaderboard_live',
        'max_peserta',
        'jeda_detik',
        'status',
        'fase',
        'soal_aktif_urutan',
        'soal_mulai_at',
        'soal_berakhir_at',
        'display_token',
        'dibuka_at',
        'ditutup_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acak_opsi' => 'boolean',
            'tampilkan_pembahasan' => 'boolean',
            'leaderboard_live' => 'boolean',
            'max_peserta' => 'integer',
            'jeda_detik' => 'integer',
            'soal_aktif_urutan' => 'integer',
            'soal_mulai_at' => 'integer',
            'soal_berakhir_at' => 'integer',
            'dibuka_at' => 'datetime',
            'ditutup_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function remidiDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remidi_dari_session_id');
    }

    public function remidi(): HasMany
    {
        return $this->hasMany(self::class, 'remidi_dari_session_id');
    }

    public function isRemidi(): bool
    {
        return $this->jenis === 'remidi';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Sesi tanpa kelas terbuka untuk semua peserta. Sesi kelas hanya untuk anggotanya.
     */
    public function scopeUntukPeserta(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('kelas_id')
                ->orWhereIn('kelas_id', function ($sub) use ($user) {
                    $sub->select('kelas_id')->from('kelas_user')->where('user_id', $user->id);
                });
        });
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function allowedParticipants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiz_session_participants', 'quiz_session_id', 'user_id')
            ->withTimestamps();
    }

    public function isUserAllowed(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (! $this->allowedParticipants()->exists()) {
            return true;
        }

        return $this->allowedParticipants()->where('users.id', $userId)->exists();
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(SessionQuestion::class)->orderBy('urutan');
    }

    public function joinedCount(): int
    {
        return $this->attempts()->count();
    }

    public function isPenuh(): bool
    {
        return $this->max_peserta !== null && $this->joinedCount() >= $this->max_peserta;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function pembahasanMode(): string
    {
        return $this->pembahasan_mode
            ?: ($this->tampilkan_pembahasan ? 'terbuka' : 'tersembunyi');
    }

    public function semuaPesertaSelesai(): bool
    {
        if ($this->status === 'selesai') {
            return true;
        }

        if (! $this->attempts()->whereNotNull('bergabung_at')->exists()) {
            return false;
        }

        return ! $this->attempts()
            ->whereNotNull('bergabung_at')
            ->where('status', '!=', 'selesai')
            ->exists();
    }

    public function pembahasanTampil(): bool
    {
        return match ($this->pembahasanMode()) {
            'terbuka' => true,
            'otomatis' => $this->semuaPesertaSelesai(),
            default => false,
        };
    }

    public function isModeTerkunci(): bool
    {
        return $this->status !== 'draft';
    }

    public function soalAktif(): ?SessionQuestion
    {
        if ($this->soal_aktif_urutan === null) {
            return null;
        }

        return $this->sessionQuestions()->where('urutan', $this->soal_aktif_urutan)->first();
    }

    public function displayUrl(): string
    {
        return route('display.show', $this->display_token);
    }
}
