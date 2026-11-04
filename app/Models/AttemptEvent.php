<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptEvent extends Model
{
    public const LABEL = [
        'fullscreen_exit' => ['pesan' => 'Keluar dari layar penuh (Toleransi 10s berjalan)', 'level' => 'warning'],
        'tab_hidden' => ['pesan' => 'Membuka tab lain / tab disembunyikan', 'level' => 'warning'],
        'blur_window' => ['pesan' => 'Berpindah ke jendela aplikasi lain', 'level' => 'warning'],
        'copy_attempt' => ['pesan' => 'Mencoba menyalin (copy) teks soal', 'level' => 'warning'],
        'fullscreen_exit_timeout' => ['pesan' => 'Toleransi habis: kuis dikumpulkan paksa & akun di-logout', 'level' => 'danger'],
    ];

    public $timestamps = false;

    protected $fillable = [
        'attempt_id',
        'tipe',
        'detail',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * @return array{pesan: string, level: string}
     */
    public static function label(string $tipe): array
    {
        return self::LABEL[$tipe] ?? ['pesan' => $tipe, 'level' => 'warning'];
    }
}
