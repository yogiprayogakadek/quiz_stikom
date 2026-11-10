<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'kelas_id',
        'organization_id',
        'judul',
        'jumlah_soal',
        'acak_opsi',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kelas_id' => 'integer',
            'jumlah_soal' => 'integer',
            'acak_opsi' => 'boolean',
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

    public function isLocked(): bool
    {
        return $this->sessions()
            ->whereIn('status', ['lobby', 'berjalan', 'selesai'])
            ->exists();
    }
}
