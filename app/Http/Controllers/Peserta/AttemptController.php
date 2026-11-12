<?php

namespace App\Http\Controllers\Peserta;

use App\Events\PesertaPeringatan;
use App\Events\SessionMonitorUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnswerRequest;
use App\Http\Requests\StoreAttemptEventRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\ParticipantLoginActivity;
use App\Models\QuizGrade;
use App\Models\User;
use App\Services\QuizEngine;
use App\Support\OrganizationContext;
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

        if ($attempt === null || ! $attempt->sudahBergabung()) {
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

        if (! $attempt->sudahBergabung()) {
            return redirect()->route('peserta.dashboard')
                ->withErrors(['gabung' => 'Gabung sesi terlebih dahulu.']);
        }

        $attempt->load('quizSession.quiz');

        if ($attempt->quizSession->mode !== 'acak') {
            return redirect()->route('peserta.session.play', $attempt->quiz_session_id);
        }

        if ($attempt->status === 'selesai') {
            return redirect()->route('peserta.attempt.result', $attempt);
        }

        if ($attempt->status === 'menunggu') {
            return redirect()->route('peserta.dashboard')
                ->withErrors(['attempt' => 'Sesi belum dimulai oleh admin. Mohon tunggu hingga admin memulai sesi.']);
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
                'tipe' => $question->tipe,
                'bobot' => $question->bobot,
                'opsi' => $opsi,
                'jawaban_option_id' => $jawaban->get($question->id)?->option_id,
                'jawaban_teks' => $jawaban->get($question->id)?->jawaban_teks,
            ];
        })->values()->all();

        // Buka lagi di soal pertama yang belum dijawab setelah refresh atau koneksi putus.
        $mulaiIndex = max(0, count($questions) - 1);
        foreach ($questions as $i => $question) {
            if (! $jawaban->has($question['id'])) {
                $mulaiIndex = $i;
                break;
            }
        }

        $data = [
            'attempt' => $attempt,
            'session' => $attempt->quizSession,
            'questions' => $questions,
            'mulai_index' => $mulaiIndex,
            'server_now' => now()->getTimestampMs(),
        ];

        return view('peserta.attempt.show', $data);
    }

    public function answer(StoreAnswerRequest $request, Attempt $attempt, QuizEngine $engine): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if (! $attempt->sudahBergabung()) {
            return response()->json(['message' => 'Gabung sesi terlebih dahulu.'], 403);
        }

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

        $attemptQuestion = $attempt->attemptQuestions()
            ->where('question_id', $questionId)
            ->with('question')
            ->first();

        if ($attemptQuestion === null) {
            return response()->json(['message' => 'Soal ini bukan bagian dari attempt Anda.'], 422);
        }

        $question = $attemptQuestion->question;
        $now = now()->getTimestampMs();
        $waktuJawab = max(0, $now - (int) $attempt->mulai_at);

        if ($question->isEssay()) {
            if ($request->filled('option_id')) {
                return response()->json(['message' => 'Soal essay tidak memakai opsi jawaban.'], 422);
            }

            $teks = trim((string) $request->input('jawaban_teks', ''));

            if ($teks === '') {
                Answer::where('attempt_id', $attempt->id)
                    ->where('question_id', $questionId)
                    ->delete();
            } else {
                Answer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $questionId],
                    [
                        'option_id' => null,
                        'jawaban_teks' => $teks,
                        'waktu_jawab_ms' => $waktuJawab,
                        'skor' => 0,
                        'dinilai_at' => null,
                        'dinilai_oleh' => null,
                        'answered_at' => now(),
                    ]
                );
            }

            $engine->siarkanDisplayTerbatas($attempt->quizSession);

            return response()->json([
                'ok' => true,
                'question_id' => $questionId,
                'jawaban_teks' => $teks,
                'answered_count' => $attempt->answers()->count(),
            ]);
        }

        $optionId = (int) $request->input('option_id');

        if ($optionId <= 0) {
            return response()->json(['message' => 'Pilih salah satu jawaban.'], 422);
        }

        if (! in_array($optionId, array_map('intval', $attemptQuestion->urutan_opsi_json), true)) {
            return response()->json(['message' => 'Opsi tidak sesuai dengan urutan soal Anda.'], 422);
        }

        Answer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $questionId],
            [
                'option_id' => $optionId,
                'jawaban_teks' => null,
                'waktu_jawab_ms' => $waktuJawab,
                'skor' => 0,
                'answered_at' => now(),
            ]
        );

        $engine->siarkanDisplayTerbatas($attempt->quizSession);
        SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, [
            'action' => 'answer_submitted',
            'user_id' => $attempt->user_id,
            'question_id' => $questionId,
        ]);

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
            SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, [
                'action' => 'attempt_selesai',
                'user_id' => $attempt->user_id,
            ]);
        }

        return redirect()->route('peserta.attempt.result', $attempt);
    }

    public function pelanggaran(Request $request, Attempt $attempt, QuizEngine $engine): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->loadMissing(['user', 'quizSession']);

        $kejadian = $attempt->events()->create([
            'tipe' => 'fullscreen_exit_timeout',
            'detail' => 'Kuis dikumpulkan otomatis karena keluar dari layar penuh dan melewati batas toleransi.',
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        $session = $attempt->quizSession;
        SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, ['action' => 'violation', 'user_id' => $attempt->user_id]);

        if ($session && $session->display_token) {
            $label = AttemptEvent::label('fullscreen_exit_timeout');

            PesertaPeringatan::safeDispatch($session->display_token, [
                'id' => $kejadian->id,
                'nama' => $attempt->user?->nama ?? 'Peserta',
                'username' => $attempt->user?->username ?? '-',
                'tipe' => 'fullscreen_exit_timeout',
                'pesan' => $label['pesan'],
                'level' => $label['level'],
                'waktu' => now()->timezone(app(OrganizationContext::class)->timezone())->format('H:i:s'),
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

                $skor = $totalSoal > 0 ? (int) round($benar / $totalSoal * 100) : 0;

                $attempt->update([
                    'status' => 'selesai',
                    'selesai_at' => now()->getTimestampMs(),
                    'jumlah_benar' => $benar,
                    'skor_total' => $skor,
                    'nilai' => max(0, min(100, $skor)),
                ]);
            }

            $freshSession = $attempt->quizSession->fresh();
            $engine->bukaPembahasanOtomatis($freshSession);
            $engine->siarkanDisplay($freshSession);
        }

        $user = $request->user();
        if ($user !== null) {
            ParticipantLoginActivity::recordAndBroadcast($user, 'logout', 'fullscreen_violation');
            User::whereKey($user->id)->update(['current_session_token' => null]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('peringatan', 'Ujian Anda telah dikumpulkan otomatis dan Anda dikeluarkan dari sistem karena keluar dari mode layar penuh.');
    }

    /**
     * Status ringkas untuk halaman pengerjaan. Dipakai polling agar peserta
     * tahu ketika admin mengumpulkan paksa atau mengeluarkannya dari sesi.
     */
    public function status(Request $request, Attempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->forceFill(['last_seen_at' => now()->getTimestampMs()])->save();

        return response()->json([
            'status' => $attempt->status,
            'selesai' => $attempt->status === 'selesai',
        ]);
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
        $tampilkan = $session->pembahasanTampil();

        $rincian = $attemptQuestions->map(function (AttemptQuestion $aq) use ($jawaban, $tampilkan) {
            $question = $aq->question;
            $jawabanSaya = $jawaban->get($question->id);
            $benar = $question->correctOption();
            $essay = $question->isEssay();

            return [
                'teks' => $question->teks,
                'tipe' => $question->tipe,
                'bobot' => $question->bobot,
                'jawaban_saya' => $essay ? $jawabanSaya?->jawaban_teks : $jawabanSaya?->option?->teks,
                'jawaban_benar' => $essay
                    ? ($tampilkan ? $question->kunci_essay : null)
                    : ($tampilkan ? $benar?->teks : null),
                'pembahasan' => $tampilkan ? $question->pembahasan : null,
                'benar' => $essay ? null : ($jawabanSaya?->option?->is_correct === true),
                'dinilai_at' => $essay ? $jawabanSaya?->dinilai_at : null,
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

    public function event(StoreAttemptEventRequest $request, Attempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->loadMissing(['user', 'quizSession']);

        $tipe = $request->input('tipe');

        // Durasi keluar layar ditempelkan ke kejadian terakhir, bukan kejadian baru,
        // supaya tidak menambah hitungan pelanggaran.
        if ($tipe === 'kembali') {
            $attempt->events()
                ->whereIn('tipe', ['fullscreen_exit', 'tab_hidden', 'blur_window'])
                ->whereNull('durasi_detik')
                ->latest('id')
                ->first()
                ?->update(['durasi_detik' => max(0, $request->integer('durasi_detik'))]);

            return response()->json(['ok' => true]);
        }

        $detail = $request->input('detail');

        $kejadian = $attempt->events()->create([
            'tipe' => $tipe,
            'detail' => $detail,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        $jumlah = $attempt->events()->count();

        $session = $attempt->quizSession;
        if ($session) {
            SessionMonitorUpdated::safeDispatch($session->id, ['action' => 'violation', 'user_id' => $attempt->user_id]);

            if ($session->display_token) {
                $label = AttemptEvent::label($tipe);

                PesertaPeringatan::safeDispatch($session->display_token, [
                    'id' => $kejadian->id,
                    'nama' => $attempt->user?->nama ?? 'Peserta',
                    'username' => $attempt->user?->username ?? '-',
                    'tipe' => $tipe,
                    'pesan' => $label['pesan'],
                    'level' => $label['level'],
                    'waktu' => now()->timezone(app(OrganizationContext::class)->timezone())->format('H:i:s'),
                    'jumlah_pelanggaran' => $jumlah,
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'jumlah' => $jumlah,
            'perlu_ditinjau' => $jumlah >= 3,
        ], 201);
    }
}
