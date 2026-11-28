<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'option_id',
        'selected_option_ids_json',
        'jawaban_teks',
        'jawaban_parts_json',
        'jawaban_data_json',
        'waktu_jawab_ms',
        'typing_metrics_json',
        'skor',
        'catatan_pengajar',
        'ai_suggested_score',
        'ai_confidence',
        'ai_feedback',
        'ai_suggestions_json',
        'ai_model',
        'ai_reviewed_at',
        'similarity_score',
        'similarity_data',
        'similarity_checked_at',
        'ai_generated_score',
        'ai_generated_reason',
        'ai_generated_at',
        'dinilai_at',
        'dinilai_oleh',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_option_ids_json' => 'array',
            'jawaban_parts_json' => 'array',
            'jawaban_data_json' => 'array',
            'waktu_jawab_ms' => 'integer',
            'typing_metrics_json' => 'array',
            'skor' => 'integer',
            'ai_suggested_score' => 'integer',
            'ai_confidence' => 'integer',
            'ai_suggestions_json' => 'array',
            'similarity_score' => 'integer',
            'similarity_data' => 'array',
            'similarity_checked_at' => 'datetime',
            'ai_generated_score' => 'integer',
            'ai_generated_at' => 'datetime',
            'ai_reviewed_at' => 'datetime',
            'dinilai_at' => 'datetime',
            'dinilai_oleh' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }

    /**
     * @return array{flagged: bool, label: string, reason: string}|null
     */
    public function indikasiTranskripsiEksternal(): ?array
    {
        $metrics = $this->typing_metrics_json;
        if (! is_array($metrics) || $metrics === []) {
            return null;
        }

        $panjang = mb_strlen(trim((string) $this->jawaban_teks));
        if ($panjang < 120) {
            return null;
        }

        $jedaAwalMs = max(0, (int) ($metrics['first_input_delay_ms'] ?? 0));
        $durasiKetikMs = max(0, (int) ($metrics['typing_duration_ms'] ?? 0));
        $totalTombol = max(0, (int) ($metrics['total_keys'] ?? 0));
        $hapusTombol = max(0, (int) ($metrics['backspace_keys'] ?? 0));
        $pasteCount = max(0, (int) ($metrics['paste_events'] ?? 0));
        $rasioHapus = $totalTombol > 0 ? ($hapusTombol / $totalTombol) : 0.0;
        $karakterPerDetik = $durasiKetikMs > 0 ? ($panjang / ($durasiKetikMs / 1000)) : 0.0;

        if ($jedaAwalMs >= 40000 && $totalTombol >= 80 && $rasioHapus < 0.03) {
            $detikDiam = (int) round($jedaAwalMs / 1000);

            return [
                'flagged' => true,
                'label' => 'Indikasi salin perangkat lain',
                'reason' => "Jeda awal {$detikDiam} detik tanpa ketikan lalu mengetik {$panjang} karakter dengan koreksi sangat rendah (".round($rasioHapus * 100, 1).'%).',
            ];
        }

        if ($durasiKetikMs > 0 && $karakterPerDetik >= 9.0 && $pasteCount === 0) {
            return [
                'flagged' => true,
                'label' => 'Indikasi salin perangkat lain',
                'reason' => 'Kecepatan input '.round($karakterPerDetik, 1).' karakter/detik melampaui ritme penulisan mandiri.',
            ];
        }

        return null;
    }
}
