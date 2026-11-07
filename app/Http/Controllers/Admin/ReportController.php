<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\QuizSession;
use App\Services\QuizEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function session(Request $request, QuizSession $session): View
    {
        $session->load('quiz');

        $tinjau = $request->boolean('tinjau');

        $attempts = $session->attempts()
            ->bergabung()
            ->with('user')
            ->withCount([
                'events',
                'answers',
                'answers as essay_belum_dinilai' => fn ($q) => $q
                    ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                    ->whereNull('dinilai_at'),
            ])
            ->when($tinjau, fn ($q) => $q->has('events', '>=', 3))
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        $totalAttempt = $session->attempts()->bergabung()->count();
        $selesai = $session->attempts()->bergabung()->where('status', 'selesai')->count();
        $perluTinjau = $session->attempts()->bergabung()->has('events', '>=', 3)->count();

        $essayBelumDinilai = Answer::query()
            ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
            ->whereHas('question', fn ($q) => $q->where('tipe', 'essay'))
            ->whereNull('dinilai_at')
            ->count();

        $statistikSoal = $this->statistikSoal($session);

        return view('admin.reports.session', compact(
            'session',
            'attempts',
            'totalAttempt',
            'selesai',
            'perluTinjau',
            'essayBelumDinilai',
            'statistikSoal',
            'tinjau',
        ));
    }

    public function attempt(Attempt $attempt): View
    {
        $attempt->load(['user', 'quizSession.quiz', 'events']);

        $attemptQuestions = $attempt->attemptQuestions()->with('question.options')->get();
        $jawaban = $attempt->answers()->with('option')->get()->keyBy('question_id');

        $rincian = $attemptQuestions->map(function (AttemptQuestion $aq) use ($jawaban) {
            $question = $aq->question;
            $answer = $jawaban->get($question->id);
            $essay = $question->isEssay();

            return [
                'question_id' => $question->id,
                'urutan' => $aq->urutan,
                'tipe' => $question->tipe,
                'bobot' => $question->bobot,
                'teks' => $question->teks,
                'jawaban_saya' => $essay ? $answer?->jawaban_teks : $answer?->option?->teks,
                'jawaban_benar' => $essay ? null : $question->correctOption()?->teks,
                'kunci_essay' => $essay ? $question->kunci_essay : null,
                'benar' => $essay ? null : ($answer?->option?->is_correct === true),
                'dinilai_at' => $answer?->dinilai_at,
                'skor' => $answer?->skor ?? 0,
            ];
        });

        return view('admin.reports.attempt', compact('attempt', 'rincian'));
    }

    public function nilaiEssay(Request $request, Attempt $attempt, QuizEngine $engine): RedirectResponse
    {
        $attempt->loadMissing('quizSession');

        abort_unless($attempt->quizSession->mode === 'acak', 422);
        abort_unless($attempt->status === 'selesai', 422);

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'nilai.required' => 'Isi minimal satu nilai essay.',
            'nilai.*.integer' => 'Nilai essay harus berupa angka bulat.',
            'nilai.*.min' => 'Nilai essay tidak boleh negatif.',
        ]);

        $questions = $attempt->attemptQuestions()
            ->with('question')
            ->get()
            ->pluck('question', 'question_id');

        $answers = $attempt->answers()->get()->keyBy('question_id');
        $errors = [];

        foreach ($data['nilai'] as $questionId => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }

            $question = $questions->get((int) $questionId);

            if ($question === null || ! $question->isEssay()) {
                $errors['nilai.'.$questionId] = 'Soal ini bukan soal essay pada attempt tersebut.';

                continue;
            }

            if ((int) $nilai > (int) $question->bobot) {
                $errors['nilai.'.$questionId] = "Nilai maksimal soal ini {$question->bobot}.";

                continue;
            }

            $answer = $answers->get($question->id);

            if ($answer === null) {
                if ((int) $nilai > 0) {
                    $errors['nilai.'.$questionId] = 'Peserta tidak menjawab soal ini.';
                }

                continue;
            }

            $answer->update([
                'skor' => (int) $nilai,
                'dinilai_at' => now(),
                'dinilai_oleh' => $request->user()->id,
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $engine->hitungSkorAcak($attempt);

        // Siarkan pembaruan nilai essay secara real-time ke peserta dan monitor admin
        foreach ($data['nilai'] as $qid => $val) {
            if ($val !== null && $val !== '') {
                \App\Events\EssayDinilai::safeDispatch(
                    $attempt->quiz_session_id,
                    $attempt->user_id,
                    $attempt->id,
                    (int) $qid,
                    (int) $val
                );
            }
        }
        \App\Events\SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, [
            'action' => 'essay_graded',
            'attempt_id' => $attempt->id,
        ]);

        Log::notice('Admin menilai jawaban essay.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'attempt_id' => $attempt->id,
            'nilai' => $data['nilai'],
        ]);

        return back()->with('sukses', 'Nilai essay disimpan dan skor attempt diperbarui.');
    }

    public function export(QuizSession $session): StreamedResponse
    {
        $attempts = $session->attempts()
            ->bergabung()
            ->with('user')
            ->withCount([
                'events',
                'answers as essay_belum_dinilai' => fn ($q) => $q
                    ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                    ->whereNull('dinilai_at'),
            ])
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        return response()->streamDownload(function () use ($attempts, $session) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'nama',
                'username',
                'status',
                'jumlah_benar',
                'poin',
                'poin_maksimal',
                'skor',
                'essay_belum_dinilai',
                'mulai',
                'selesai',
                'kejadian_tercatat',
                'perlu_ditinjau',
            ]);

            foreach ($attempts as $attempt) {
                fputcsv($out, [
                    $attempt->user?->nama ?? '(peserta dihapus)',
                    $attempt->user?->username ?? '',
                    $attempt->status,
                    $attempt->jumlah_benar,
                    $attempt->poin_total,
                    $attempt->poin_maksimal,
                    $attempt->skor_total,
                    $attempt->essay_belum_dinilai,
                    $this->waktu($attempt->mulai_at),
                    $this->waktu($attempt->selesai_at),
                    $attempt->events_count,
                    $attempt->events_count >= 3 ? 'ya' : 'tidak',
                ]);
            }

            fclose($out);
        }, 'nilai-'.$this->slug($session->nama).'.csv', ['Content-Type' => 'text/csv']);
    }

    public function resetAttempt(Request $request, Attempt $attempt): RedirectResponse
    {
        $request->validate([
            'konfirmasi' => ['required', 'in:HAPUS'],
        ], [
            'konfirmasi.required' => 'Ketik HAPUS untuk mengonfirmasi reset attempt.',
            'konfirmasi.in' => 'Ketik HAPUS (huruf kapital) untuk mengonfirmasi.',
        ]);

        $sessionId = $attempt->quiz_session_id;
        $nama = $attempt->user?->nama;
        $atas = $attempt->user?->username;

        Log::notice('Admin mereset attempt peserta.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'attempt_id' => $attempt->id,
            'peserta' => $nama,
            'peserta_username' => $atas,
            'quiz_session_id' => $sessionId,
            'alasan' => $request->input('catatan'),
        ]);

        $attempt->delete();

        return redirect()
            ->route('admin.reports.session', $sessionId)
            ->with('sukses', 'Attempt '.($nama ?? '').' direset. Catatan tersimpan di log aplikasi.');
    }

    private function statistikSoal(QuizSession $session): array
    {
        if ($session->mode === 'serempak') {
            $ids = $session->sessionQuestions()->pluck('question_id')->all();
        } else {
            $ids = AttemptQuestion::query()
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->distinct()
                ->pluck('question_id')
                ->all();
        }

        if ($ids === []) {
            return [];
        }

        $questions = Question::withTrashed()
            ->with('options')
            ->whereIn('id', $ids)
            ->where('tipe', 'pilihan_ganda')
            ->get();

        return $questions->map(function (Question $question) use ($session) {
            $peserta = AttemptQuestion::query()
                ->where('question_id', $question->id)
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->count();

            $benar = Answer::query()
                ->where('answers.question_id', $question->id)
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->join('options', 'options.id', '=', 'answers.option_id')
                ->where('options.is_correct', true)
                ->count();

            return [
                'teks' => $question->teks,
                'peserta' => $peserta,
                'benar' => $benar,
                'persen' => $peserta > 0 ? round($benar / $peserta * 100, 1) : 0.0,
            ];
        })->sortBy('persen')->values()->all();
    }

    private function waktu(?int $ms): string
    {
        if ($ms === null) {
            return '';
        }

        return date('Y-m-d H:i:s', intdiv($ms, 1000));
    }

    private function slug(string $teks): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($teks)) ?: 'sesi';
    }
}
