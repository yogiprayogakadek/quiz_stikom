<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnswerRequest;
use App\Http\Requests\StoreAttemptEventRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Events\PesertaPeringatan;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttemptController extends Controller
{
    public function start(Request $request, int $session, QuizEngine $engine): RedirectResponse
    {
        $attempt = Attempt::where('user_id', $request->user()->id)
            ->where('quiz_session_id', $session)
            ->first();

        if ($attempt === null) {
            return redirect()->route('peserta.dashboard')
                ->withErrors(['gabung' => 'Gabung sesi terlebih dahulu.']);
        }

        if ($attempt->status === 'selesai') {
            return redirect()->route('peserta.attempt.result', $attempt);
        }

        if ($attempt->quizSession->mode === 'serempak') {
            return redirect()->route('peserta.session.play', $session);
        }

        $engine->mulaiAcak($attempt);

        return redirect()->route('peserta.attempt.show', $attempt);
    }

    public function show(Attempt $attempt, QuizEngine $engine): View|RedirectResponse
    {
        abort_unless($attempt->user_id === request()->user()->id, 403);

        $attempt->load('quizSession.quiz');

        if ($attempt->quizSession->mode !== 'acak') {
            return redirect()->route('peserta.session.play', $attempt->quiz_session_id);
        }

        if ($attempt->status === 'selesai') {
            return redirect()->route('peserta.attempt.result', $attempt);
        }

        if ($attempt->status === 'menunggu') {
            return redirect()->route('peserta.dashboard')
                ->withErrors(['attempt' => 'Sesi belum dimulai oleh admin.']);
        }

        if ($attempt->isLewatBatas()) {
            $engine->selesaikanAcak($attempt);

            return redirect()->route('peserta.attempt.result', $attempt);
        }

        $bankSoal = $attempt->attemptQuestions()->with('question.options')->get();

        $jawaban = $attempt->answers()->get()->keyBy('question_id');

        $questions = $bankSoal->map(function (AttemptQuestion $aq) use ($jawaban) {
            $question = $aq->question;
            $opsi = collect($aq->urutan_opsi_json)
                ->map(fn ($id) => $question->options->firstWhere('id', (int) $id))
                ->filter()
                ->values()
                ->map(fn ($o) => ['id' => $o->id, 'teks' => $o->teks])
                ->all();

            return [
                'id' => $question->id,
                'urutan' => $aq->urutan,
                'teks' => $question->teks,
                'opsi' => $opsi,
                'jawaban_option_id' => $jawaban->get($question->id)?->option_id,
            ];
        })->values()->all();

        $data = [
            'attempt' => $attempt,
            'session' => $attempt->quizSession,
            'questions' => $questions,
            'server_now' => now()->getTimestampMs(),
        ];

        return view('peserta.attempt.show', $data);
    }

    public function answer(StoreAnswerRequest $request, Attempt $attempt, QuizEngine $engine): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->load('quizSession');

        if ($attempt->quizSession->mode !== 'acak') {
            return response()->json(['message' => 'Mode sesi ini bukan Acak.'], 422);
        }

        if ($attempt->status !== 'berjalan') {
            return response()->json(['message' => 'Attempt sudah selesai dan tidak menerima jawaban.'], 403);
        }

        if ($attempt->isLewatBatas()) {
            $engine->selesaikanAcak($attempt);

            return response()->json([
                'message' => 'Waktu habis. Attempt sudah dikunci.',
                'selesai' => true,
            ], 403);
        }

        $questionId = (int) $request->input('question_id');
        $optionId = (int) $request->input('option_id');

        $attemptQuestion = $attempt->attemptQuestions()->where('question_id', $questionId)->first();

        if ($attemptQuestion === null) {
            return response()->json(['message' => 'Soal ini bukan bagian dari attempt Anda.'], 422);
        }

        if (! in_array($optionId, array_map('intval', $attemptQuestion->urutan_opsi_json), true)) {
            return response()->json(['message' => 'Opsi tidak sesuai dengan urutan soal Anda.'], 422);
        }

        $now = now()->getTimestampMs();

        Answer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $questionId],
            [
                'option_id' => $optionId,
                'waktu_jawab_ms' => max(0, $now - (int) $attempt->mulai_at),
                'skor' => 0,
                'answered_at' => now(),
            ]
        );

        $engine->siarkanDisplayTerbatas($attempt->quizSession);

        return response()->json([
            'ok' => true,
            'question_id' => $questionId,
            'option_id' => $optionId,
            'answered_count' => $attempt->answers()->count(),
        ]);
    }

    public function finish(Request $request, Attempt $attempt, QuizEngine $engine): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->load('quizSession');

        if ($attempt->quizSession->mode === 'acak') {
            $engine->selesaikanAcak($attempt);
            $engine->siarkanDisplay($attempt->quizSession->fresh());
        }

        return redirect()->route('peserta.attempt.result', $attempt);
    }

    public function pelanggaran(Request $request, Attempt $attempt, QuizEngine $engine): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->loadMissing(['user', 'quizSession']);

        $attempt->events()->create([
            'tipe' => 'fullscreen_exit_timeout',
            'detail' => 'Kuis dikumpulkan otomatis karena keluar dari layar penuh dan melewati batas toleransi.',
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        $session = $attempt->quizSession;
        if ($session && $session->display_token) {
            PesertaPeringatan::safeDispatch($session->display_token, [
                'nama' => $attempt->user?->nama ?? 'Peserta',
                'username' => $attempt->user?->username ?? '-',
                'tipe' => 'fullscreen_exit_timeout',
                'pesan' => 'Toleransi habis: kuis dikumpulkan otomatis & akun di-logout.',
                'level' => 'danger',
                'waktu' => now()->format('H:i:s'),
                'jumlah_pelanggaran' => $attempt->events()->count(),
            ]);
        }

        if ($attempt->status === 'berjalan') {
            if ($attempt->quizSession->mode === 'acak') {
                $engine->selesaikanAcak($attempt);
            } else {
                $totalSoal = $attempt->quizSession->sessionQuestions()->count();
                $benar = Answer::query()
                    ->where('answers.attempt_id', $attempt->id)
                    ->join('options', 'options.id', '=', 'answers.option_id')
                    ->where('options.is_correct', true)
                    ->count();

                $attempt->update([
                    'status' => 'selesai',
                    'selesai_at' => now()->getTimestampMs(),
                    'jumlah_benar' => $benar,
                    'skor_total' => $totalSoal > 0 ? (int) round($benar / $totalSoal * 100) : 0,
                ]);
            }

            $engine->siarkanDisplay($attempt->quizSession->fresh());
        }

        $user = $request->user();
        if ($user !== null) {
            User::whereKey($user->id)->update(['current_session_token' => null]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('peringatan', 'Ujian Anda telah dikumpulkan otomatis dan Anda dikeluarkan dari sistem karena keluar dari mode layar penuh.');
    }

    public function result(Request $request, Attempt $attempt): View|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->load('quizSession.quiz');

        if ($attempt->status !== 'selesai') {
            return redirect()->route('peserta.dashboard')
                ->withErrors(['attempt' => 'Attempt ini belum selesai.']);
        }

        $session = $attempt->quizSession;
        $attemptQuestions = $attempt->attemptQuestions()->with('question.options')->get();
        $jawaban = $attempt->answers()->with('option')->get()->keyBy('question_id');
        $tampilkan = $session->tampilkan_pembahasan;

        $rincian = $attemptQuestions->map(function (AttemptQuestion $aq) use ($jawaban, $tampilkan) {
            $question = $aq->question;
            $jawabanSaya = $jawaban->get($question->id);
            $benar = $question->correctOption();

            return [
                'teks' => $question->teks,
                'jawaban_saya' => $jawabanSaya?->option?->teks,
                'jawaban_benar' => $tampilkan ? $benar?->teks : null,
                'pembahasan' => $tampilkan ? $question->pembahasan : null,
                'benar' => $jawabanSaya?->option?->is_correct === true,
                'skor' => $jawabanSaya?->skor ?? 0,
                'urutan' => $aq->urutan,
            ];
        });

        return view('peserta.hasil', [
            'attempt' => $attempt,
            'session' => $session,
            'rincian' => $rincian,
            'tampilkan' => $tampilkan,
            'total_soal' => $attemptQuestions->count(),
        ]);
    }

    public function event(StoreAttemptEventRequest $request, Attempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->loadMissing(['user', 'quizSession']);

        $tipe = $request->input('tipe');
        $detail = $request->input('detail');

        $attempt->events()->create([
            'tipe' => $tipe,
            'detail' => $detail,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        $jumlah = $attempt->events()->count();

        $session = $attempt->quizSession;
        if ($session && $session->display_token) {
            $labelAksi = match ($tipe) {
                'fullscreen_exit' => 'Keluar dari layar penuh (Toleransi 10s berjalan)',
                'tab_hidden' => 'Membuka tab lain / tab disembunyikan',
                'blur_window' => 'Berpindah ke jendela aplikasi lain',
                'copy_attempt' => 'Mencoba menyalin (copy) teks soal',
                'fullscreen_exit_timeout' => 'Toleransi habis: kuis dikumpulkan paksa & akun di-logout',
                default => $tipe,
            };

            PesertaPeringatan::safeDispatch($session->display_token, [
                'nama' => $attempt->user?->nama ?? 'Peserta',
                'username' => $attempt->user?->username ?? '-',
                'tipe' => $tipe,
                'pesan' => $labelAksi,
                'level' => in_array($tipe, ['fullscreen_exit_timeout'], true) ? 'danger' : 'warning',
                'waktu' => now()->format('H:i:s'),
                'jumlah_pelanggaran' => $jumlah,
            ]);
        }

        return response()->json([
            'ok' => true,
            'jumlah' => $jumlah,
            'perlu_ditinjau' => $jumlah >= 3,
        ], 201);
    }
}
