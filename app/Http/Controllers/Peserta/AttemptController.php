<?php

namespace App\Http\Controllers\Peserta;

use App\Events\SessionMonitorUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnswerRequest;
use App\Http\Requests\StoreAttemptEventRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\QuizGrade;
use App\Services\ProctoringService;
use App\Services\QuizEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttemptController extends Controller
{
    public function start(Request $request, int $session, QuizEngine $engine): RedirectResponse
    {
        $attempt = Attempt::where('user_id', $request->user()->id)
            ->where('quiz_session_id', $session)
            ->first();

        if ($attempt === null || ! $attempt->sudahMengikuti()) {
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

        if (! $attempt->sudahMengikuti()) {
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
                'jawaban_parts' => $jawaban->get($question->id)?->jawaban_parts_json ?? [],
                'petunjuk' => $question->petunjuk_json ?? [],
                'sub_soal' => $question->sub_soal_json ?? [],
                'ragu_ragu' => (bool) $aq->ragu_ragu,
                'highlight_soal' => (bool) $aq->highlight_soal,
                'highlight_opsi' => array_map('intval', $aq->highlight_opsi_json ?? []),
            ];
        })->values()->all();

        // Buka lagi di soal pertama yang belum lengkap setelah refresh atau koneksi putus.
        $mulaiIndex = max(0, count($questions) - 1);
        foreach ($questions as $i => $question) {
            $answer = $jawaban->get($question['id']);
            $lengkap = $answer !== null;

            if ($lengkap && $question['tipe'] === 'essay' && $question['sub_soal'] !== []) {
                $parts = $answer->jawaban_parts_json ?? [];

                foreach ($question['sub_soal'] as $bagian) {
                    if (trim((string) ($parts[$bagian['label']] ?? '')) === '') {
                        $lengkap = false;
                        break;
                    }
                }
            }

            if (! $lengkap) {
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

        if (! $attempt->sudahMengikuti()) {
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

            $subSoal = $question->sub_soal_json ?? [];
            $partKey = trim((string) $request->input('part_key', ''));
            $teks = trim((string) $request->input('jawaban_teks', ''));

            if ($subSoal !== [] && $partKey === '') {
                return response()->json(['message' => 'Jawaban soal ini harus dikirim per bagian.'], 422);
            }

            if ($partKey !== '' && ! collect($subSoal)->pluck('label')->contains($partKey)) {
                return response()->json(['message' => 'Bagian soal tidak dikenali.'], 422);
            }

            $answer = Answer::where('attempt_id', $attempt->id)
                ->where('question_id', $questionId)
                ->first();
            $parts = $answer?->jawaban_parts_json ?? [];

            if ($partKey !== '') {
                if ($teks === '') {
                    unset($parts[$partKey]);
                } else {
                    $parts[$partKey] = $teks;
                }

                $labelValid = collect($subSoal)->pluck('label')->map(fn ($label) => (string) $label)->all();
                $parts = array_intersect_key($parts, array_flip($labelValid));
                $teks = $this->gabungkanJawabanParts($subSoal, $parts);
            }

            if ($teks === '') {
                $answer?->delete();
                $parts = [];
            } else {
                Answer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $questionId],
                    [
                        'option_id' => null,
                        'jawaban_teks' => $teks,
                        'jawaban_parts_json' => $partKey !== '' ? $parts : null,
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
                'part_key' => $partKey !== '' ? $partKey : null,
                'jawaban_teks' => $teks,
                'jawaban_parts' => $parts,
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

    public function pelanggaran(Request $request, Attempt $attempt, ProctoringService $proctoring): JsonResponse|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

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

    /**
     * Status ringkas untuk halaman pengerjaan. Dipakai polling agar peserta
     * tahu ketika admin mengumpulkan paksa atau mengeluarkannya dari sesi.
     */
    public function status(Request $request, Attempt $attempt, ProctoringService $proctoring): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $proctoring->periksaKedaluwarsa($attempt);
        $attempt->refresh();

        $attempt->forceFill(['last_seen_at' => now()->getTimestampMs()])->save();

        return response()->json([
            'status' => $attempt->status,
            'selesai' => $attempt->status === 'selesai',
            'batas_akhir_at' => $attempt->batas_akhir_at,
            'server_now' => now()->getTimestampMs(),
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
                'petunjuk' => $question->petunjuk_json ?? [],
                'sub_soal' => $question->sub_soal_json ?? [],
                'jawaban_parts' => $essay ? ($jawabanSaya?->jawaban_parts_json ?? []) : [],
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
            'tampilkanFeedback' => $attempt->needsFeedbackPrompt() && ! session('feedback_ditunda'),
        ]);
    }

    public function event(StoreAttemptEventRequest $request, Attempt $attempt, ProctoringService $proctoring): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if ($attempt->status !== 'berjalan') {
            return response()->json(['message' => 'Attempt sudah selesai.'], 403);
        }

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

            $proctoring->tutupPeringatan($attempt);

            return response()->json(['ok' => true]);
        }

        $hasil = $proctoring->catat(
            $attempt,
            $tipe,
            $request->input('detail'),
            $request->ip(),
        );

        return response()->json([
            'ok' => true,
            'jumlah' => $hasil['jumlah'],
            'maks' => $hasil['maks'],
            'durasi' => $hasil['durasi'],
            'perlu_ditinjau' => $hasil['batas_tercapai'],
        ], 201);
    }

    public function tandai(Request $request, Attempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->loadMissing('quizSession');

        if ($attempt->status !== 'berjalan' || $attempt->quizSession?->mode !== 'acak') {
            return response()->json(['message' => 'Penanda soal hanya tersedia saat Mode Acak berjalan.'], 422);
        }

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'ragu_ragu' => ['sometimes', 'boolean'],
            'highlight_soal' => ['sometimes', 'boolean'],
            'highlight_opsi' => ['sometimes', 'array'],
            'highlight_opsi.*' => ['integer'],
        ]);

        $attemptQuestion = $attempt->attemptQuestions()
            ->where('question_id', (int) $data['question_id'])
            ->first();

        if ($attemptQuestion === null) {
            return response()->json(['message' => 'Soal ini bukan bagian dari attempt Anda.'], 422);
        }

        $updates = [];

        if (array_key_exists('ragu_ragu', $data)) {
            $updates['ragu_ragu'] = (bool) $data['ragu_ragu'];
        }

        if (array_key_exists('highlight_soal', $data)) {
            $updates['highlight_soal'] = (bool) $data['highlight_soal'];
        }

        if (array_key_exists('highlight_opsi', $data)) {
            $valid = array_map('intval', $attemptQuestion->urutan_opsi_json ?? []);
            $updates['highlight_opsi_json'] = collect($data['highlight_opsi'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $valid, true))
                ->unique()
                ->values()
                ->all();
        }

        if ($updates !== []) {
            $attemptQuestion->update($updates);
        }

        return response()->json([
            'ok' => true,
            'question_id' => $attemptQuestion->question_id,
            'ragu_ragu' => (bool) $attemptQuestion->ragu_ragu,
            'highlight_soal' => (bool) $attemptQuestion->highlight_soal,
            'highlight_opsi' => array_map('intval', $attemptQuestion->highlight_opsi_json ?? []),
        ]);
    }

    private function gabungkanJawabanParts(array $subSoal, array $parts): string
    {
        $bagian = [];

        foreach ($subSoal as $soal) {
            $label = (string) ($soal['label'] ?? '');
            $teks = trim((string) ($parts[$label] ?? ''));

            if ($teks !== '') {
                $bagian[] = $label.":\n".$teks;
            }
        }

        return implode("\n\n", $bagian);
    }
}
