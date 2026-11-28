<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuizSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionRegradeService
{
    public function __construct(
        private readonly QuizEngine $engine,
        private readonly NilaiService $nilaiService,
    ) {}

    /**
     * @param  array{mode_ralat: string, correct_option_id?: int|null, correct_option_ids?: array<int, int|string>, alasan?: string|null}  $data
     * @return array{affected_attempts: int}
     */
    public function regrade(QuizSession $session, Question $question, array $data, int $actorId): array
    {
        if ($question->isEssay()) {
            throw ValidationException::withMessages([
                'soal' => 'Soal essay dinilai manual per peserta di halaman detail attempt.',
            ]);
        }

        $dipakaiSesi = (int) $question->quiz_id === (int) $session->quiz_id
            || $session->sessionQuestions()->where('question_id', $question->id)->exists()
            || Attempt::where('quiz_session_id', $session->id)
                ->whereHas('attemptQuestions', fn ($q) => $q->where('question_id', $question->id))
                ->exists();

        if (! $dipakaiSesi) {
            throw ValidationException::withMessages([
                'soal' => 'Soal ini tidak termasuk dalam sesi yang dipilih.',
            ]);
        }

        $mode = (string) ($data['mode_ralat'] ?? '');
        $alasan = trim((string) ($data['alasan'] ?? ''));
        $question->load('options');

        $affectedAttempts = 0;

        DB::transaction(function () use ($session, $question, $mode, $data, $alasan, $actorId, &$affectedAttempts) {
            $metadata = [
                'quiz_session_id' => $session->id,
                'mode_ralat' => $mode,
                'alasan' => $alasan !== '' ? $alasan : null,
            ];

            if ($mode === 'ubah_kunci') {
                if ($question->tipe === 'pilihan_majemuk') {
                    $ids = array_values(array_unique(array_map('intval', (array) ($data['correct_option_ids'] ?? []))));
                    $validIds = $question->options->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $terpilih = array_values(array_intersect($ids, $validIds));

                    if ($terpilih === []) {
                        throw ValidationException::withMessages([
                            'correct_option_ids' => 'Pilih minimal satu opsi kunci yang benar.',
                        ]);
                    }

                    foreach ($question->options as $opsi) {
                        $opsi->update(['is_correct' => in_array((int) $opsi->id, $terpilih, true)]);
                    }

                    $metadata['correct_option_ids'] = $terpilih;
                } else {
                    $correctOptionId = (int) ($data['correct_option_id'] ?? 0);
                    $targetOpsi = $question->options->firstWhere('id', $correctOptionId);

                    if ($targetOpsi === null) {
                        throw ValidationException::withMessages([
                            'correct_option_id' => 'Opsi kunci baru tidak ditemukan pada soal ini.',
                        ]);
                    }

                    foreach ($question->options as $opsi) {
                        $opsi->update(['is_correct' => (int) $opsi->id === $correctOptionId]);
                    }

                    $metadata['correct_option_id'] = $correctOptionId;
                }

                $question->update(['is_bonus' => false]);
            } elseif ($mode === 'anulir_bonus') {
                $question->update(['is_bonus' => true]);
            } elseif ($mode === 'batalkan_bonus') {
                $question->update(['is_bonus' => false]);
            } else {
                throw ValidationException::withMessages([
                    'mode_ralat' => 'Jenis koreksi soal tidak dikenali.',
                ]);
            }

            $question->refresh()->load('options');

            $attempts = Attempt::query()
                ->where('organization_id', $session->organization_id)
                ->where('status', 'selesai')
                ->whereHas('attemptQuestions', fn ($q) => $q->where('question_id', $question->id))
                ->with('quizSession')
                ->get();

            $affectedSessions = collect([$session->id => $session]);

            foreach ($attempts as $attempt) {
                $attemptSession = $attempt->quizSession;
                if ($attemptSession === null) {
                    continue;
                }

                $affectedSessions->put($attemptSession->id, $attemptSession);

                if ($attemptSession->mode === 'acak') {
                    $this->engine->hitungSkorAcak($attempt);
                } else {
                    $this->perbaruiJawabanSerempak($attempt, $question);
                    $this->engine->hitungUlangSkorSerempak($attempt->id);
                }

                $affectedAttempts++;
            }

            foreach ($affectedSessions as $sesiTerdampak) {
                $this->nilaiService->sinkronkanSetelahRalat($sesiTerdampak, $actorId);
            }

            $metadata['affected_attempts'] = $affectedAttempts;

            AuditLog::record(
                'question_key_regraded',
                'question',
                $question->id,
                $metadata,
                $session->organization_id,
            );
        });

        return ['affected_attempts' => $affectedAttempts];
    }

    private function perbaruiJawabanSerempak(Attempt $attempt, Question $question): void
    {
        $answer = Answer::where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->with('option')
            ->first();

        if ($answer === null) {
            return;
        }

        if ($question->is_bonus) {
            $answer->update(['skor' => 1000]);

            return;
        }

        $tepat = method_exists($question, 'evaluasiOtomatis')
            ? ($question->evaluasiOtomatis($answer)['benar_penuh'] ?? false)
            : ($answer->option?->is_correct === true);

        $skor = 0;
        if ($tepat) {
            $durasiMs = max(1, (int) $question->durasi_detik * 1000);
            $rasio = min(1, max(0, (float) $answer->waktu_jawab_ms / $durasiMs));
            $skor = (int) round(1000 * (1 - $rasio / 2));
        }

        $answer->update(['skor' => $skor]);
    }
}
