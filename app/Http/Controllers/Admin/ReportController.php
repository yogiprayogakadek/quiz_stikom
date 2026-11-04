<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\QuizSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function session(Request $request, QuizSession $session): View
    {
        $session->load('quiz');

        $tinjau = $request->boolean('tinjau');

        $attempts = $session->attempts()
            ->with('user')
            ->withCount(['events', 'answers'])
            ->when($tinjau, fn ($q) => $q->has('events', '>=', 3))
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        $totalAttempt = $session->attempts()->count();
        $selesai = $session->attempts()->where('status', 'selesai')->count();
        $perluTinjau = $session->attempts()->has('events', '>=', 3)->count();

        $statistikSoal = $this->statistikSoal($session);

        return view('admin.reports.session', compact(
            'session',
            'attempts',
            'totalAttempt',
            'selesai',
            'perluTinjau',
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

            return [
                'urutan' => $aq->urutan,
                'teks' => $question->teks,
                'jawaban_saya' => $answer?->option?->teks,
                'jawaban_benar' => $question->correctOption()?->teks,
                'benar' => $answer?->option?->is_correct === true,
                'skor' => $answer?->skor ?? 0,
            ];
        });

        return view('admin.reports.attempt', compact('attempt', 'rincian'));
    }

    public function export(QuizSession $session): StreamedResponse
    {
        $attempts = $session->attempts()
            ->with('user')
            ->withCount('events')
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
                'skor',
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
                    $attempt->skor_total,
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

        $questions = Question::withTrashed()->with('options')->whereIn('id', $ids)->get();

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
