<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\QuizGrade;
use App\Models\QuizSession;
use Illuminate\Support\Facades\DB;

class NilaiService
{
    public function attempt(Attempt $attempt): int
    {
        if ($attempt->nilai !== null) {
            return max(0, min(100, (int) $attempt->nilai));
        }

        $attempt->loadMissing('quizSession');

        if ($attempt->quizSession?->mode === 'acak') {
            return max(0, min(100, (int) $attempt->skor_total));
        }

        $total = max(1, $attempt->quizSession?->sessionQuestions()->count() ?? 1);

        return max(0, min(100, (int) round($attempt->skor_total / ($total * 1000) * 100)));
    }

    public function untukSesi(QuizSession $session, int $userId): ?int
    {
        $attempt = $session->attempts()
            ->where('user_id', $userId)
            ->where('status', 'selesai')
            ->first();

        return $attempt === null ? null : $this->attempt($attempt);
    }

    public function terbaikPerKuis(int $quizId, int $userId): int
    {
        $attempts = Attempt::query()
            ->where('user_id', $userId)
            ->where('status', 'selesai')
            ->whereHas('quizSession', fn ($q) => $q->where('quiz_id', $quizId))
            ->get();

        return (int) $attempts->map(fn (Attempt $a) => $this->attempt($a))->max();
    }

    /**
     * Terapkan nilai terbaik sesi asli dan remidi untuk peserta yang sudah selesai.
     * Nilai tidak pernah turun jika fungsi ini dipanggil ulang.
     *
     * @return array{diterapkan: int, dilewati: int}
     */
    public function terapkanUntukSesi(QuizSession $remidi, ?array $userIds = null, ?int $actorId = null): array
    {
        $remidi->loadMissing('remidiDari');
        $sesiAwal = $remidi->remidiDari;

        if (! $remidi->isRemidi() || $sesiAwal === null) {
            return ['diterapkan' => 0, 'dilewati' => 0];
        }

        $query = $remidi->attempts()->where('status', 'selesai');

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }

        $attemptRemidi = $query->get()->keyBy('user_id');
        $ids = $attemptRemidi->keys()->all();

        if ($ids === []) {
            return ['diterapkan' => 0, 'dilewati' => 0];
        }

        $attemptAwal = $sesiAwal->attempts()
            ->whereIn('user_id', $ids)
            ->where('status', 'selesai')
            ->get()
            ->keyBy('user_id');

        $grades = QuizGrade::query()
            ->where('sesi_awal_id', $sesiAwal->id)
            ->whereIn('user_id', $ids)
            ->get()
            ->keyBy('user_id');

        $diterapkan = 0;
        $dilewati = 0;

        DB::transaction(function () use ($ids, $attemptAwal, $attemptRemidi, $grades, $sesiAwal, $remidi, $actorId, &$diterapkan, &$dilewati) {
            foreach ($ids as $userId) {
                $awal = $attemptAwal->get($userId);
                $remidiAttempt = $attemptRemidi->get($userId);

                if ($awal === null || $remidiAttempt === null) {
                    $dilewati++;

                    continue;
                }

                $nilaiAwal = $this->attempt($awal);
                $nilaiRemidi = $this->attempt($remidiAttempt);
                $grade = $grades->get($userId);
                $nilaiAkhir = max($nilaiAwal, $nilaiRemidi, $grade?->nilai_akhir ?? 0);

                QuizGrade::updateOrCreate(
                    ['sesi_awal_id' => $sesiAwal->id, 'user_id' => $userId],
                    [
                        'organization_id' => $sesiAwal->organization_id,
                        'sesi_remidi_id' => $remidi->id,
                        'nilai_awal' => $nilaiAwal,
                        'nilai_remidi' => $nilaiRemidi,
                        'nilai_akhir' => $nilaiAkhir,
                        'sumber' => $nilaiAkhir > $nilaiAwal ? 'remidi' : 'awal',
                        'ditetapkan_oleh' => $actorId ?? $grade?->ditetapkan_oleh,
                        'ditetapkan_at' => now(),
                    ],
                );

                $diterapkan++;
            }
        });

        return ['diterapkan' => $diterapkan, 'dilewati' => $dilewati];
    }
}
