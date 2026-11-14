<?php

namespace App\Http\Controllers\Peserta;

use App\Events\SessionMonitorUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnswerRequest;
use App\Jobs\CloseQuestionJob;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\QuizGrade;
use App\Models\QuizSession;
use App\Services\ProctoringService;
use App\Services\QuizEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function join(Request $request, QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        $engine->gabung($session, $request->user());

        if ($session->mode === 'serempak') {
            return redirect()->route('peserta.session.play', $session)
                ->with('sukses', 'Anda sudah bergabung. Tunggu admin memulai sesi.');
        }

        return redirect()->route('peserta.dashboard')
            ->with('sukses', 'Anda sudah bergabung di sesi '.$session->nama.'.');
    }

    public function play(Request $request, QuizSession $session): View|RedirectResponse
    {
        $attempt = $this->attemptUntuk($request, $session);

        if ($attempt === null || ! $attempt->sudahMengikuti()) {
            return redirect()->route('peserta.dashboard')
                ->withErrors(['gabung' => 'Gabung sesi terlebih dahulu.']);
        }

        if ($session->mode !== 'serempak') {
            if ($attempt->status === 'selesai') {
                return redirect()->route('peserta.attempt.result', $attempt);
            }

            return redirect()->route('peserta.attempt.show', $attempt);
        }

        if ($attempt->status === 'selesai' || $session->status === 'selesai') {
            return redirect()->route('peserta.session.result', $session);
        }

        return view('peserta.session.play', [
            'session' => $session->load('quiz'),
            'attempt' => $attempt,
            'stateUrl' => route('peserta.session.state', $session),
            'answerUrl' => route('peserta.session.answer', $session),
            'resultUrl' => route('peserta.session.result', $session),
            'channel' => 'session.'.$session->id,
        ]);
    }

    public function state(Request $request, QuizSession $session, QuizEngine $engine, ProctoringService $proctoring): JsonResponse
    {
        $attempt = $this->attemptUntuk($request, $session);

        if ($attempt === null || ! $attempt->sudahMengikuti()) {
            return response()->json(['message' => 'Attempt tidak ditemukan.'], 404);
        }

        $proctoring->periksaKedaluwarsa($attempt);
        $attempt->refresh();

        $attempt->forceFill(['last_seen_at' => now()->getTimestampMs()])->save();

        $base = [
            'status' => $session->status,
            'fase' => $session->fase,
            'server_now' => now()->getTimestampMs(),
            'attempt_status' => $attempt->status,
            'skor_total' => $attempt->skor_total,
            'jumlah_benar' => $attempt->jumlah_benar,
            'total_peserta' => $session->attempts()->mengikuti()->count(),
            'result_url' => route('peserta.session.result', $session),
        ];

        if ($session->status === 'lobby') {
            return response()->json($base + [
                'pesan' => 'Menunggu admin memulai sesi.',
                'peserta' => $session->attempts()->with('user')->get()->pluck('user.nama')->all(),
            ]);
        }

        if ($session->status === 'selesai' || $attempt->status === 'selesai') {
            return response()->json($base + ['selesai' => true]);
        }

        if ($attempt->status !== 'berjalan') {
            return response()->json($base + ['pesan' => 'Attempt belum berjalan.']);
        }

        $soalAktif = $engine->soalAktifUntukAttempt($session, $attempt);

        $sudahMenjawab = $session->soal_aktif_urutan !== null
            ? Answer::query()
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->where('question_id', $soalAktif['id'] ?? 0)
                ->count()
            : 0;

        $base = $base + [
            'urutan' => $session->soal_aktif_urutan,
            'total_soal' => $session->sessionQuestions()->count(),
            'ends_at' => $session->soal_berakhir_at,
            'soal_mulai_at' => $session->soal_mulai_at,
            'sudah_menjawab' => $sudahMenjawab,
        ];

        if ($session->fase === 'soal_terbuka' && $soalAktif !== null) {
            return response()->json($base + [
                'soal' => $soalAktif,
                'jawaban_option_id' => $soalAktif['jawaban_option_id'],
            ]);
        }

        if ($session->fase === 'jeda') {
            $rincian = $this->rincianSoalJeda($attempt, $session);

            return response()->json($base + [
                'soal' => $soalAktif,
                'jeda' => $rincian,
                'jeda_detik' => $session->jeda_detik,
            ]);
        }

        return response()->json($base);
    }

    public function answer(StoreAnswerRequest $request, QuizSession $session, QuizEngine $engine): JsonResponse
    {
        $attempt = $this->attemptUntuk($request, $session);

        if ($attempt === null || ! $attempt->sudahMengikuti()) {
            return response()->json(['message' => 'Attempt tidak ditemukan.'], 404);
        }

        if ($session->mode !== 'serempak') {
            return response()->json(['message' => 'Sesi ini bukan Mode Serempak.'], 422);
        }

        if ($attempt->status !== 'berjalan' || $session->status !== 'berjalan') {
            return response()->json(['message' => 'Sesi tidak sedang berjalan.'], 403);
        }

        if ($session->fase !== 'soal_terbuka' || $session->soal_aktif_urutan === null) {
            return response()->json(['message' => 'Soal sudah ditutup. Jawaban tidak diterima.'], 403);
        }

        $now = now()->getTimestampMs();

        if ($session->soal_berakhir_at !== null && $now > $session->soal_berakhir_at) {
            return response()->json(['message' => 'Waktu soal habis. Jawaban tidak diterima.'], 403);
        }

        $attemptQuestion = $attempt->attemptQuestions()
            ->where('urutan', $session->soal_aktif_urutan)
            ->first();

        if ($attemptQuestion === null) {
            return response()->json(['message' => 'Soal aktif tidak ditemukan untuk attempt ini.'], 422);
        }

        if ((int) $request->input('question_id') !== $attemptQuestion->question_id) {
            return response()->json(['message' => 'Ini bukan soal yang sedang terbuka.'], 422);
        }

        $attemptQuestion->loadMissing('question');

        if ($attemptQuestion->question?->isEssay()) {
            return response()->json(['message' => 'Soal essay hanya didukung di Mode Acak.'], 422);
        }

        $optionId = (int) $request->input('option_id');

        if (! in_array($optionId, array_map('intval', $attemptQuestion->urutan_opsi_json), true)) {
            return response()->json(['message' => 'Opsi tidak sesuai dengan urutan soal Anda.'], 422);
        }

        Answer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $attemptQuestion->question_id],
            [
                'option_id' => $optionId,
                'waktu_jawab_ms' => max(0, $now - (int) $session->soal_mulai_at),
                'skor' => 0,
                'answered_at' => now(),
            ]
        );

        $totalPeserta = $session->attempts()->mengikuti()->count();
        $sudahMenjawab = Answer::query()
            ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
            ->where('question_id', $attemptQuestion->question_id)
            ->count();

        if ($totalPeserta > 0 && $sudahMenjawab >= $totalPeserta) {
            CloseQuestionJob::dispatch($session->id, $session->soal_aktif_urutan);
        }

        $engine->siarkanDisplayTerbatas($session);
        SessionMonitorUpdated::safeDispatch($session->id, [
            'action' => 'answer_submitted',
            'user_id' => $attempt->user_id,
            'question_id' => $attemptQuestion->question_id,
        ]);

        return response()->json([
            'ok' => true,
            'option_id' => $optionId,
            'sudah_menjawab' => $sudahMenjawab,
            'total_peserta' => $totalPeserta,
        ]);
    }

    public function result(Request $request, QuizSession $session): View|RedirectResponse
    {
        $attempt = $this->attemptUntuk($request, $session);

        if ($attempt === null) {
            return redirect()->route('peserta.dashboard');
        }

        if ($attempt->status !== 'selesai' && $session->status !== 'selesai') {
            return redirect()->route('peserta.session.play', $session);
        }

        $attempt->loadMissing('quizSession.quiz');
        $session->loadMissing('quiz');

        $attemptQuestions = $attempt->attemptQuestions()->with('question.options')->get();
        $jawaban = $attempt->answers()->with('option')->get()->keyBy('question_id');
        $tampilkan = $session->pembahasanTampil();

        $rincian = $attemptQuestions->map(function (AttemptQuestion $aq) use ($jawaban, $tampilkan) {
            $question = $aq->question;
            $jawabanSaya = $jawaban->get($question->id);
            $benar = $question->correctOption();

            return [
                'teks' => $question->teks,
                'tipe' => $question->tipe,
                'bobot' => $question->bobot,
                'jawaban_saya' => $jawabanSaya?->option?->teks,
                'jawaban_benar' => $tampilkan ? $benar?->teks : null,
                'pembahasan' => $tampilkan ? $question->pembahasan : null,
                'benar' => $jawabanSaya?->option?->is_correct === true,
                'dinilai_at' => null,
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
            'nilaiAkhir' => QuizGrade::untukAttempt($attempt),
        ]);
    }

    private function attemptUntuk(Request $request, QuizSession $session): ?Attempt
    {
        return Attempt::where('user_id', $request->user()->id)
            ->where('quiz_session_id', $session->id)
            ->first();
    }

    private function rincianSoalJeda(Attempt $attempt, QuizSession $session): array
    {
        $attemptQuestion = $attempt->attemptQuestions()
            ->where('urutan', $session->soal_aktif_urutan)
            ->with('question.options')
            ->first();

        if ($attemptQuestion === null) {
            return [];
        }

        $question = $attemptQuestion->question;
        $answer = Answer::where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->with('option')
            ->first();

        $benar = $question->options->firstWhere('is_correct', true);

        return [
            'jawaban_benar' => $benar?->teks,
            'jawaban_saya' => $answer?->option?->teks,
            'benar' => $answer?->option?->is_correct === true,
            'skor_soal' => $answer?->skor ?? 0,
            'pembahasan' => $session->pembahasanTampil() ? $question->pembahasan : null,
        ];
    }

    public function pelanggaran(Request $request, QuizSession $session, ProctoringService $proctoring): JsonResponse|RedirectResponse
    {
        $attempt = $this->attemptUntuk($request, $session);

        if ($attempt === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Attempt tidak ditemukan.'], 404)
                : redirect()->route('peserta.dashboard');
        }

        $hasil = $proctoring->catat(
            $attempt,
            'fullscreen_exit_timeout',
            'Kuis dikumpulkan otomatis karena keluar dari layar penuh dan melewati batas toleransi.',
            $request->ip(),
        );

        if (! $hasil['batas_tercapai']) {
            $proctoring->tutupPeringatan($attempt);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'final' => false,
                    'jumlah' => $hasil['jumlah'],
                    'maks' => $hasil['maks'],
                    'durasi' => $hasil['durasi'],
                ]);
            }

            return back()->with('sukses', 'Kamu masih bisa melanjutkan ujian.');
        }

        $proctoring->finalisasi($attempt);
        $redirect = $proctoring->logout(
            $request,
            'Ujian Anda telah dikumpulkan otomatis dan Anda dikeluarkan dari sistem karena melewati batas pelanggaran.',
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'final' => true,
                'redirect_url' => $redirect->getTargetUrl(),
            ]);
        }

        return $redirect;
    }
}
