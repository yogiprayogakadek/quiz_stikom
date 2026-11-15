<?php

namespace App\Services;

use App\Models\Attempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptResetService
{
    public function reset(Attempt $attempt, string $mode, ?int $durationMinutes = null): void
    {
        if ($mode === 'fresh') {
            DB::transaction(fn () => $attempt->delete());

            return;
        }

        if ($mode !== 'resume') {
            throw ValidationException::withMessages(['reset_mode' => 'Pilihan pengulangan tidak valid.']);
        }

        DB::transaction(function () use ($attempt, $durationMinutes) {
            $locked = Attempt::query()->with('quizSession')->lockForUpdate()->findOrFail($attempt->id);
            $session = $locked->quizSession;

            if ($session?->mode !== 'acak') {
                throw ValidationException::withMessages(['reset_mode' => 'Lanjut dari jawaban terakhir hanya tersedia untuk Mode Acak.']);
            }

            if ($session->status !== 'berjalan') {
                throw ValidationException::withMessages(['reset_mode' => 'Attempt hanya dapat dilanjutkan saat sesi masih berjalan.']);
            }

            if ($durationMinutes === null || $durationMinutes < 1 || $durationMinutes > 600) {
                throw ValidationException::withMessages(['retry_duration_minutes' => 'Isi durasi ulang antara 1 dan 600 menit.']);
            }

            if ($session->isTerjadwal() && ($session->scheduledEndMs() ?? 0) <= now()->getTimestampMs()) {
                throw ValidationException::withMessages(['reset_mode' => 'Jadwal sesi sudah berakhir. Perpanjang jadwal atau buat sesi baru.']);
            }

            $locked->answers()->update([
                'skor' => 0,
                'ai_suggested_score' => null,
                'ai_confidence' => null,
                'ai_feedback' => null,
                'ai_model' => null,
                'ai_reviewed_at' => null,
                'dinilai_at' => null,
                'dinilai_oleh' => null,
            ]);
            $locked->events()->delete();
            $locked->forceFill([
                'status' => 'menunggu',
                'mulai_at' => null,
                'batas_akhir_at' => null,
                'retry_duration_detik' => $durationMinutes * 60,
                'selesai_at' => null,
                'jumlah_benar' => 0,
                'skor_total' => 0,
                'nilai' => null,
                'poin_total' => 0,
                'poin_maksimal' => 0,
                'jumlah_pelanggaran' => 0,
                'total_pengurangan_waktu_detik' => 0,
                'last_pelanggaran_action' => null,
                'peringatan_mulai_at' => null,
                'last_seen_at' => null,
            ])->save();
        });
    }
}
