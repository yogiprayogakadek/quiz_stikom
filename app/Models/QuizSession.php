<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\ExamTools;
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
        'kode_akses',
        'mode',
        'jenis',
        'remidi_dari_session_id',
        'remidi_soal',
        'acak_opsi',
        'navigasi_sekuensial',
        'timer_per_soal',
        'pisah_konteks_soal',
        'watermark_layar',
        'tampilkan_pembahasan',
        'pembahasan_mode',
        'nilai_ditampilkan',
        'leaderboard_live',
        'max_peserta',
        'max_pelanggaran',
        'durasi_peringatan',
        'pelanggaran_action',
        'pelanggaran_penalty_seconds',
        'clipboard_policy',
        'webcam_proctoring',
        'screen_proctoring',
        'exam_tools_json',
        'jeda_detik',
        'durasi_total_detik',
        'timing_mode',
        'scheduled_start_at',
        'scheduled_end_at',
        'batas_telat_menit',
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
            'navigasi_sekuensial' => 'boolean',
            'timer_per_soal' => 'boolean',
            'pisah_konteks_soal' => 'boolean',
            'watermark_layar' => 'boolean',
            'webcam_proctoring' => 'boolean',
            'tampilkan_pembahasan' => 'boolean',
            'nilai_ditampilkan' => 'boolean',
            'leaderboard_live' => 'boolean',
            'max_peserta' => 'integer',
            'max_pelanggaran' => 'integer',
            'durasi_peringatan' => 'integer',
            'pelanggaran_penalty_seconds' => 'integer',
            'exam_tools_json' => 'array',
            'jeda_detik' => 'integer',
            'durasi_total_detik' => 'integer',
            'batas_telat_menit' => 'integer',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
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

    public function durasiPeringatan(): int
    {
        return max(5, min(300, (int) ($this->durasi_peringatan ?: 30)));
    }

    public function isTerjadwal(): bool
    {
        return $this->timing_mode === 'scheduled';
    }

    public function scheduledEndMs(): ?int
    {
        return $this->scheduled_end_at?->getTimestampMs();
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

        if (! $this->attempts()->mengikuti()->exists()) {
            return false;
        }

        return ! $this->attempts()
            ->mengikuti()
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

    public function nilaiTampil(): bool
    {
        return (bool) $this->nilai_ditampilkan;
    }

    public function nilaiSiapDipublikasikan(): bool
    {
        if ($this->status !== 'selesai' || $this->attempts()->mengikuti()->where('status', '!=', 'selesai')->exists()) {
            return false;
        }

        return $this->essayMenungguPenilaian() === 0;
    }

    public function essayMenungguPenilaian(): int
    {
        return Answer::query()
            ->whereHas('attempt', fn (Builder $query) => $query
                ->mengikuti()
                ->where('quiz_session_id', $this->id))
            ->whereHas('question', fn (Builder $query) => $query->where('tipe', 'essay'))
            ->whereNotNull('jawaban_teks')
            ->where('jawaban_teks', '!=', '')
            ->whereNull('dinilai_at')
            ->count();
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

    /** @return list<string> */
    public function examTools(): array
    {
        return ExamTools::forQuiz($this->exam_tools_json, (bool) $this->quiz?->is_percobaan);
    }

    public function butuhKodeAkses(): bool
    {
        return filled($this->kode_akses);
    }

    public function verifikasiKodeAkses(?string $input): bool
    {
        if (! $this->butuhKodeAkses()) {
            return true;
        }

        return hash_equals(
            mb_strtoupper(trim((string) $this->kode_akses)),
            mb_strtoupper(trim((string) $input))
        );
    }

    public function sudahLewatBatasTelat(): bool
    {
        if ($this->batas_telat_menit === null || (int) $this->batas_telat_menit <= 0 || $this->status !== 'berjalan') {
            return false;
        }

        $acuan = $this->scheduled_start_at ?? $this->dibuka_at;
        if ($acuan === null) {
            return false;
        }

        return now()->greaterThan($acuan->copy()->addMinutes((int) $this->batas_telat_menit));
    }

    public function pakaiIsolasiSatuSoal(): bool
    {
        return $this->mode === 'acak' && ($this->navigasi_sekuensial || $this->timer_per_soal);
    }

    public function pakaiScreenProctoring(): bool
    {
        return in_array($this->screen_proctoring ?? 'off', ['optional_mobile', 'desktop_only'], true);
    }
}
