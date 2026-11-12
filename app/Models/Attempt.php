<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'organization_id',
        'quiz_session_id',
        'status',
        'mulai_at',
        'batas_akhir_at',
        'selesai_at',
        'bergabung_at',
        'last_seen_at',
        'jumlah_benar',
        'skor_total',
        'nilai',
        'poin_total',
        'poin_maksimal',
    ];

    protected function casts(): array
    {
        return [
            'mulai_at' => 'integer',
            'batas_akhir_at' => 'integer',
            'selesai_at' => 'integer',
            'bergabung_at' => 'datetime',
            'last_seen_at' => 'integer',
            'jumlah_benar' => 'integer',
            'skor_total' => 'integer',
            'nilai' => 'integer',
            'poin_total' => 'integer',
            'poin_maksimal' => 'integer',
        ];
    }

    /**
     * Admin bisa menyiapkan attempt lebih awal, tapi peserta baru dianggap ikut
     * setelah menekan Gabung sendiri.
     */
    public function sudahBergabung(): bool
    {
        return $this->bergabung_at !== null;
    }

    /**
     * Batasi ke peserta yang benar-benar menekan Gabung, bukan attempt yang
     * hanya disiapkan admin dari daftar hadir.
     */
    public function scopeBergabung(Builder $query): Builder
    {
        return $query->whereNotNull('bergabung_at');
    }

    public function isOnline(int $jendelaMs = 30000): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at >= now()->getTimestampMs() - $jendelaMs;
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
