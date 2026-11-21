<?php

namespace App\Http\Controllers\Admin;

use App\Events\EssayDinilai;
use App\Events\SessionMonitorUpdated;
use App\Exceptions\AiRequestException;
use App\Http\Controllers\Controller;
use App\Jobs\ScanSessionPlagiarismJob;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuizSession;
use App\Services\AttemptResetService;
use App\Services\EssayGrader;
use App\Services\NilaiService;
use App\Services\QuizEngine;
use App\Services\TextSimilarityService;
use App\Support\OrganizationContext;
use App\Support\QuestionContent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function session(Request $request, QuizSession $session): View
    {
        $session->load('quiz.sourceQuiz', 'remidi');

        $tinjau = $request->boolean('tinjau');

        $attempts = $this->attemptsLaporan($session, $tinjau);

        $totalAttempt = $session->attempts()->mengikuti()->count();
        $selesai = $session->attempts()->mengikuti()->where('status', 'selesai')->count();
        $perluTinjau = $session->attempts()->mengikuti()->has('events', '>=', 3)->count();

        $essayBelumDinilai = Answer::query()
            ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
            ->whereHas('question', fn ($q) => $q->where('tipe', 'essay'))
            ->whereNull('dinilai_at')
            ->count();

        $statistikSoal = $this->statistikSoal($session);
        $hasEssay = Question::where('quiz_id', $session->quiz_id)->where('tipe', 'essay')->exists();
        $indikasiPlagiat = Answer::query()
            ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
            ->whereHas('question', fn ($q) => $q->where('tipe', 'essay'))
            ->where('similarity_score', '>=', 50)
            ->count();

        return view('admin.reports.session', compact(
            'session',
            'attempts',
            'totalAttempt',
            'selesai',
            'perluTinjau',
            'essayBelumDinilai',
            'indikasiPlagiat',
            'hasEssay',
            'statistikSoal',
            'tinjau',
        ));
    }

    public function beritaAcara(QuizSession $session): View
    {
        $session->load(['quiz.kelas', 'kelas']);

        $attempts = $session->attempts()
            ->mengikuti()
            ->with('user')
            ->withCount(['events', 'answers'])
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        $totalPeserta = $attempts->count();
        $selesai = $attempts->where('status', 'selesai')->count();
        $sedangMengerjakan = $attempts->where('status', 'berjalan')->count();
        $pelanggaran = $attempts->sum('events_count');
        $skorRataRata = $totalPeserta > 0 ? round($attempts->avg('skor_total'), 1) : 0;
        $skorTertinggi = $totalPeserta > 0 ? $attempts->max('skor_total') : 0;
        $skorTerendah = $totalPeserta > 0 ? $attempts->min('skor_total') : 0;

        return view('admin.reports.berita-acara', compact(
            'session',
            'attempts',
            'totalPeserta',
            'selesai',
            'sedangMengerjakan',
            'pelanggaran',
            'skorRataRata',
            'skorTertinggi',
            'skorTerendah'
        ));
    }

    public function attempt(Request $request, Attempt $attempt): View
    {
        $attempt->load(['user', 'quizSession.quiz', 'events']);

        $attemptQuestions = $attempt->attemptQuestions()->with('question.options')->get();
        $jawaban = $attempt->answers()->with('option')->get()->keyBy('question_id');
        $history = AuditLog::query()
            ->where('organization_id', $attempt->organization_id)
            ->where('subject_type', 'answer')
            ->whereIn('subject_id', $jawaban->pluck('id'))
            ->whereIn('action', ['essay_score_changed', 'essay_ai_suggested'])
            ->with('actor')
            ->latest()
            ->get()
            ->groupBy('subject_id');

        $rincian = $attemptQuestions->map(function (AttemptQuestion $aq) use ($jawaban, $history) {
            $question = $aq->question;
            $answer = $jawaban->get($question->id);
            $essay = $question->isEssay();
            $dijawab = $essay ? filled($answer?->jawaban_teks) : $answer !== null;
            $riwayat = $answer ? ($history->get($answer->id) ?? collect()) : collect();
            $saranTerakhir = $riwayat->firstWhere('action', 'essay_ai_suggested');

            return [
                'question_id' => $question->id,
                'answer_id' => $answer?->id,
                'urutan' => $aq->urutan,
                'tipe' => $question->tipe,
                'bobot' => $question->bobot,
                'teks' => $question->teks,
                'content_html' => QuestionContent::renderBlocks($question->content_blocks_json ?? []),
                'petunjuk' => $question->petunjuk_json ?? [],
                'sub_soal' => collect($question->subSoalTerstruktur())->map(fn (array $part) => [
                    ...$part,
                    'content_html' => QuestionContent::renderBlocks($part['content_blocks'] ?? []),
                ])->all(),
                'jawaban_parts' => $essay ? ($answer?->jawaban_parts_json ?? []) : [],
                'jawaban_data' => $essay ? ($answer?->jawaban_data_json ?? []) : [],
                'answer_format' => QuestionContent::definition($question)['format'],
                'answer_config' => QuestionContent::definition($question)['config'],
                'jawaban_saya' => $essay ? $answer?->jawaban_teks : $answer?->option?->teks,
                'jawaban_benar' => $essay ? null : $question->correctOption()?->teks,
                'kunci_essay' => $essay ? $question->kunci_essay : null,
                'rubrik_essay' => $essay ? ($question->rubrik_essay ?? []) : [],
                'benar' => $essay ? null : ($answer?->option?->is_correct === true),
                'dinilai_at' => $answer?->dinilai_at,
                'skor' => $answer?->skor ?? 0,
                'catatan_pengajar' => $answer?->catatan_pengajar,
                'dijawab' => $dijawab,
                'status' => ! $essay ? null : ($answer?->dinilai_at ? 'graded' : ($dijawab ? 'pending' : 'unanswered')),
                'ai_suggested_score' => $answer?->ai_suggested_score,
                'ai_confidence' => $answer?->ai_confidence,
                'ai_feedback' => $answer?->ai_feedback,
                'ai_reviewed_at' => $answer?->ai_reviewed_at,
                'ai_mode' => $saranTerakhir?->metadata['mode'] ?? null,
                'similarity_score' => $answer?->similarity_score,
                'similarity_data' => $answer?->similarity_data ?? [],
                'similarity_checked_at' => $answer?->similarity_checked_at,
                'ai_generated_score' => $answer?->ai_generated_score,
                'ai_generated_reason' => $answer?->ai_generated_reason,
                'ai_generated_at' => $answer?->ai_generated_at,
                'history' => $riwayat,
            ];
        });

        $essayRincian = $rincian->where('tipe', 'essay')->values();

        $progresEssay = [
            'graded' => $essayRincian->where('status', 'graded')->count(),
            'pending' => $essayRincian->where('status', 'pending')->count(),
            'unanswered' => $essayRincian->where('status', 'unanswered')->count(),
        ];
        $progresEssay['total'] = $essayRincian->count();
        $progresEssay['persen'] = $progresEssay['total'] > 0
            ? (int) round($progresEssay['graded'] / $progresEssay['total'] * 100)
            : 0;

        $ruangPenilaian = $attempt->status === 'selesai'
            && $attempt->quizSession->mode === 'acak'
            && $essayRincian->isNotEmpty();

        $saranTujuan = $essayRincian
            ->filter(fn (array $baris) => $baris['dijawab'] && $baris['ai_reviewed_at'] === null)
            ->map(fn (array $baris) => [
                'id' => $baris['answer_id'],
                'url' => route('admin.answers.ai-suggestion', $baris['answer_id']),
            ])
            ->values();

        $saranEssay = [
            'total' => $essayRincian->where('dijawab', true)->count(),
            'tersedia' => $essayRincian->where('dijawab', true)->whereNotNull('ai_reviewed_at')->count(),
            'tanpa' => $saranTujuan->count(),
        ];
        $saranEssay['persen'] = $saranEssay['total'] > 0
            ? (int) round($saranEssay['tersedia'] / $saranEssay['total'] * 100)
            : 0;
        $saranTerisi = $essayRincian
            ->filter(fn (array $baris) => $baris['dijawab'] && $baris['ai_reviewed_at'] !== null);
        $saranEssay['poin'] = $saranTerisi->sum(fn (array $baris) => (int) $baris['ai_suggested_score']);
        $saranEssay['poin_maksimal'] = $saranTerisi->sum('bobot');

        $soalDiminta = filter_var($request->query('soal'), FILTER_VALIDATE_INT);
        $aktifEssay = ($soalDiminta !== false ? $essayRincian->firstWhere('question_id', $soalDiminta) : null)
            ?? $essayRincian->firstWhere('status', 'pending')
            ?? $essayRincian->first();

        $indeksAktif = $aktifEssay === null
            ? false
            : $essayRincian->search(fn (array $baris) => $baris['question_id'] === $aktifEssay['question_id']);

        $essaySebelumnya = $indeksAktif !== false && $indeksAktif > 0
            ? $essayRincian[$indeksAktif - 1]
            : null;

        $essayBerikutnya = $indeksAktif !== false
            ? $essayRincian->get($indeksAktif + 1)
            : null;

        $pesertaBerikutnya = $ruangPenilaian
            ? Attempt::query()
                ->where('quiz_session_id', $attempt->quiz_session_id)
                ->whereKeyNot($attempt->id)
                ->where('status', 'selesai')
                ->whereHas('answers', fn ($query) => $query
                    ->whereNotNull('jawaban_teks')
                    ->where('jawaban_teks', '!=', '')
                    ->whereNull('dinilai_at')
                    ->whereHas('question', fn ($soal) => $soal->where('tipe', 'essay')))
                ->orderBy('id')
                ->first()
            : null;

        return view('admin.reports.attempt', compact(
            'attempt',
            'rincian',
            'essayRincian',
            'progresEssay',
            'ruangPenilaian',
            'aktifEssay',
            'essaySebelumnya',
            'essayBerikutnya',
            'pesertaBerikutnya',
            'saranEssay',
            'saranTujuan',
        ));
    }

    public function nilaiEssay(Request $request, Attempt $attempt, QuizEngine $engine, EssayGrader $grader): RedirectResponse
    {
        $attempt->loadMissing('quizSession');

        abort_unless($attempt->quizSession->mode === 'acak', 422);
        abort_unless($attempt->status === 'selesai', 422);

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*' => ['nullable', 'integer', 'min:0'],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:2000'],
            'after_save' => ['nullable', 'in:stay,next'],
            'focus_question_id' => ['nullable', 'integer'],
        ], [
            'nilai.required' => 'Isi minimal satu nilai essay.',
            'nilai.*.integer' => 'Nilai essay harus berupa angka bulat.',
            'nilai.*.min' => 'Nilai essay tidak boleh negatif.',
            'catatan.*.string' => 'Catatan harus berupa teks.',
            'catatan.*.max' => 'Catatan maksimal 2000 karakter.',
            'after_save.in' => 'Arah setelah menyimpan nilai tidak valid.',
        ]);

        $attemptQuestions = $attempt->attemptQuestions()
            ->with('question')
            ->get();

        $questions = $attemptQuestions->pluck('question', 'question_id');
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

            if ($answer === null || blank($answer->jawaban_teks)) {
                if ((int) $nilai > 0) {
                    $errors['nilai.'.$questionId] = 'Peserta tidak menjawab soal ini.';
                }

                continue;
            }

            $grader->applyFinal(
                $answer,
                (int) $nilai,
                $request->user()->id,
                catatan: $data['catatan'][$questionId] ?? null,
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $engine->hitungSkorAcak($attempt);
        app(NilaiService::class)->terapkanUntukSesi($attempt->quizSession);

        // Siarkan pembaruan nilai essay secara real-time ke peserta dan monitor admin
        foreach ($data['nilai'] as $qid => $val) {
            if ($val !== null && $val !== '') {
                EssayDinilai::safeDispatch(
                    $attempt->quiz_session_id,
                    $attempt->user_id,
                    $attempt->id,
                    (int) $qid,
                    (int) $val
                );
            }
        }
        SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, [
            'action' => 'essay_graded',
            'attempt_id' => $attempt->id,
        ]);

        $essayIds = $attemptQuestions
            ->filter(fn (AttemptQuestion $aq) => $aq->question?->isEssay())
            ->pluck('question_id')
            ->values();

        $focusId = (int) ($data['focus_question_id'] ?? 0);

        if (! $essayIds->contains($focusId)) {
            $focusId = (int) $essayIds->first();
        }

        $answersTerbaru = $attempt->answers()
            ->whereIn('question_id', $essayIds)
            ->get()
            ->keyBy('question_id');

        $pendingIds = $essayIds->filter(fn ($questionId) => filled($answersTerbaru->get($questionId)?->jawaban_teks)
            && $answersTerbaru->get($questionId)?->dinilai_at === null)->values();

        $tujuan = $focusId;
        $pesan = 'Nilai essay disimpan dan skor attempt diperbarui.';

        if (($data['after_save'] ?? 'stay') === 'next') {
            $posisi = $essayIds->search($focusId);
            $berikutnya = $posisi !== false ? $essayIds->get($posisi + 1) : null;
            $berikutnya ??= $pendingIds->first();

            if ($berikutnya !== null) {
                $tujuan = (int) $berikutnya;
            } else {
                $pesan = 'Seluruh jawaban essay terisi sudah dinilai.';
            }
        }

        Log::notice('Admin menilai jawaban essay.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'attempt_id' => $attempt->id,
            'nilai' => $data['nilai'],
        ]);

        return redirect()
            ->to(route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $tujuan]).'#penilaian-essay')
            ->with('sukses', $pesan);
    }

    public function saranNilaiEssay(Request $request, Answer $answer, EssayGrader $grader): RedirectResponse|JsonResponse
    {
        $answer->loadMissing(['question', 'attempt.quizSession']);
        abort_unless($answer->attempt?->quizSession && $answer->question?->isEssay(), 404);
        abort_unless($answer->attempt->status === 'selesai', 422);
        abort_unless(filled($answer->jawaban_teks), 422);

        $validated = $request->validate([
            'mode' => ['nullable', 'in:ketat,seimbang,longgar'],
        ]);
        $mode = $validated['mode'] ?? 'seimbang';

        try {
            $saran = $grader->suggest($answer, $mode);
        } catch (\Throwable $e) {
            Log::warning('Saran AI essay gagal: '.$e->getMessage(), [
                'answer_id' => $answer->id,
                'attempt_id' => $answer->attempt_id,
                'question_id' => $answer->question_id,
                'mode' => $mode,
                'transient' => $e instanceof AiRequestException && $e->transient(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'pesan' => $e->getMessage(),
                    'transient' => $e instanceof AiRequestException && $e->transient(),
                ]);
            }

            return back()->withErrors(['ai_essay' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'skor' => $saran['score'],
                'keyakinan' => $saran['confidence'],
                'umpan_balik' => $saran['feedback'],
                'mode' => $saran['mode'],
            ]);
        }

        return back()->with('sukses', 'Saran AI dibuat. Periksa hasilnya sebelum menyimpan nilai final.');
    }

    public function detectAi(Request $request, Answer $answer, EssayGrader $grader): RedirectResponse|JsonResponse
    {
        $answer->loadMissing(['question', 'attempt.quizSession']);
        abort_unless($answer->attempt?->quizSession && $answer->question?->isEssay(), 404);
        abort_unless(filled($answer->jawaban_teks), 422);

        try {
            $result = $grader->detectAi($answer);
        } catch (\Throwable $e) {
            Log::warning('Deteksi AI essay gagal: '.$e->getMessage(), [
                'answer_id' => $answer->id,
                'attempt_id' => $answer->attempt_id,
                'question_id' => $answer->question_id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'pesan' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['ai_detect' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'score' => $result['score'],
                'reason' => $result['reason'],
                'model' => $result['model'],
            ]);
        }

        return back()->with('sukses', 'Audit AI selesai.');
    }

    public function auditAndEvaluateEssay(Request $request, Answer $answer, EssayGrader $grader): RedirectResponse|JsonResponse
    {
        $answer->loadMissing(['question', 'attempt.quizSession']);
        abort_unless($answer->attempt?->quizSession && $answer->question?->isEssay(), 404);
        abort_unless(filled($answer->jawaban_teks), 422);

        try {
            $result = $grader->auditAndEvaluate($answer);
        } catch (\Throwable $e) {
            Log::warning('Audit & evaluasi essay gagal: '.$e->getMessage(), [
                'answer_id' => $answer->id,
                'attempt_id' => $answer->attempt_id,
                'question_id' => $answer->question_id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'pesan' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['ai_audit_evaluate' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'ai_detection' => $result['ai_detection'],
                'suggestions' => $result['suggestions'],
                'model' => $result['model'],
            ]);
        }

        return back()->with('sukses', 'Audit integritas & saran 3 mode selesai dibuat.');
    }

    public function scanPlagiarism(Request $request, QuizSession $session, TextSimilarityService $service): RedirectResponse|JsonResponse
    {
        (new ScanSessionPlagiarismJob($session->id))->handle($service);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'pesan' => 'Pemindaian kemiripan selesai.',
            ]);
        }

        return back()->with('sukses', 'Pemindaian kemiripan selesai.');
    }

    public function export(QuizSession $session): StreamedResponse
    {
        $attempts = $this->attemptsLaporan($session);

        $session->loadMissing('kelas');

        return response()->streamDownload(function () use ($attempts, $session) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Microsoft Excel

            fputcsv($out, [
                'peringkat',
                'nama',
                'nim_username',
                'kelas',
                'status',
                'jumlah_benar',
                'poin',
                'poin_maksimal',
                'skor_total',
                'essay_belum_dinilai',
                'saran_ai',
                'saran_ai_poin',
                'saran_ai_essay',
                'mulai',
                'selesai',
                'pelanggaran',
                'detik_di_luar_layar',
                'perlu_ditinjau',
            ]);

            $rank = 1;
            $namaKelas = $session->kelas?->nama ?? '-';

            foreach ($attempts as $attempt) {
                fputcsv($out, [
                    $rank++,
                    $attempt->user?->nama ?? '(peserta dihapus)',
                    $attempt->user?->username ?? '',
                    $namaKelas,
                    $attempt->status,
                    $attempt->jumlah_benar,
                    $attempt->poin_total,
                    $attempt->poin_maksimal,
                    $attempt->skor_total,
                    $attempt->essay_belum_dinilai,
                    $attempt->saran_ai_status === 'kosong' ? '-' : $attempt->saran_ai_status,
                    (int) ($attempt->essay_ai_poin ?? 0),
                    $attempt->essay_ai.'/'.$attempt->essay_dijawab,
                    $this->waktu($attempt->mulai_at),
                    $this->waktu($attempt->selesai_at),
                    $attempt->events_count,
                    $attempt->detik_luar ?? 0,
                    $attempt->events_count >= 3 ? 'ya' : 'tidak',
                ]);
            }

            fclose($out);
        }, 'nilai-'.$this->slug($session->nama).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function resetAttempt(Request $request, Attempt $attempt, AttemptResetService $resetter): RedirectResponse
    {
        $request->mergeIfMissing(['reset_mode' => 'fresh']);
        $request->validate([
            'reset_mode' => ['required', 'in:fresh,resume'],
            'konfirmasi' => ['nullable', 'required_if:reset_mode,fresh', 'in:HAPUS'],
            'retry_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600', 'required_if:reset_mode,resume'],
        ], [
            'reset_mode.required' => 'Pilih cara peserta mengulang.',
            'konfirmasi.required_if' => 'Ketik HAPUS untuk mengonfirmasi reset attempt.',
            'konfirmasi.in' => 'Ketik HAPUS (huruf kapital) untuk mengonfirmasi.',
            'retry_duration_minutes.required_if' => 'Isi durasi ulang untuk melanjutkan jawaban terakhir.',
            'retry_duration_minutes.min' => 'Durasi ulang minimal 1 menit.',
            'retry_duration_minutes.max' => 'Durasi ulang maksimal 600 menit.',
        ]);

        $sessionId = $attempt->quiz_session_id;
        $nama = $attempt->user?->nama;
        $atas = $attempt->user?->username;

        $mode = $request->string('reset_mode')->toString();
        $duration = $request->filled('retry_duration_minutes') ? $request->integer('retry_duration_minutes') : null;

        Log::notice('Admin membuka ulang attempt peserta.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'attempt_id' => $attempt->id,
            'peserta' => $nama,
            'peserta_username' => $atas,
            'quiz_session_id' => $sessionId,
            'alasan' => $request->input('catatan'),
            'reset_mode' => $mode,
            'retry_duration_minutes' => $duration,
        ]);

        $resetter->reset($attempt, $mode, $duration);

        return redirect()
            ->route('admin.reports.session', $sessionId)
            ->with('sukses', $mode === 'resume'
                ? 'Attempt '.($nama ?? '').' dibuka ulang dengan jawaban terakhir.'
                : 'Attempt '.($nama ?? '').' dihapus. Peserta dapat mengulang dari awal.');
    }

    private function attemptsLaporan(QuizSession $session, bool $tinjau = false): Collection
    {
        $attempts = $session->attempts()
            ->mengikuti()
            ->with('user')
            ->withCount([
                'events',
                'answers',
                'answers as essay_belum_dinilai' => fn ($q) => $q
                    ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                    ->whereNull('dinilai_at'),
                'answers as essay_dijawab' => fn ($q) => $q
                    ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                    ->whereRaw("TRIM(jawaban_teks) <> ''"),
                'answers as essay_ai' => fn ($q) => $q
                    ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                    ->whereNotNull('ai_reviewed_at'),
            ])
            ->withSum(['events as detik_luar' => fn ($q) => $q->whereNotNull('durasi_detik')], 'durasi_detik')
            ->withSum(['answers as essay_ai_poin' => fn ($q) => $q
                ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                ->whereNotNull('ai_reviewed_at')], 'ai_suggested_score')
            ->when($tinjau, fn ($q) => $q->has('events', '>=', 3))
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        $attempts->each(function (Attempt $attempt) {
            $dijawab = (int) ($attempt->essay_dijawab ?? 0);
            $terisi = (int) ($attempt->essay_ai ?? 0);

            $attempt->setAttribute('saran_ai_status', match (true) {
                $dijawab === 0 => 'kosong',
                $terisi === 0 => 'belum',
                $terisi >= $dijawab => 'sudah',
                default => 'sebagian',
            });
        });

        return $attempts;
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

        if ($questions->isEmpty()) {
            return [];
        }

        $attempts = $session->attempts()->mengikuti()->get(['id', 'skor_total']);
        $attemptIds = $attempts->pluck('id')->all();

        $answers = Answer::query()
            ->whereIn('attempt_id', $attemptIds)
            ->leftJoin('options', 'options.id', '=', 'answers.option_id')
            ->get([
                'answers.attempt_id',
                'answers.question_id',
                'answers.waktu_jawab_ms',
                'options.is_correct',
            ]);

        $penerima = AttemptQuestion::query()
            ->whereIn('attempt_id', $attemptIds)
            ->get(['attempt_id', 'question_id'])
            ->groupBy('question_id')
            ->map(fn ($rows) => $rows->pluck('attempt_id')->all());

        $benarPerSoal = $answers
            ->where('is_correct', true)
            ->groupBy('question_id')
            ->map->count();

        // Waktu per soal = selisih waktu_jawab_ms antar jawaban dalam satu attempt.
        $waktuPerSoal = [];
        foreach ($answers->groupBy('attempt_id') as $jawabanAttempt) {
            $sebelumnya = 0;
            foreach ($jawabanAttempt->sortBy('waktu_jawab_ms') as $jawaban) {
                $ms = (int) ($jawaban->waktu_jawab_ms ?? 0);
                $waktuPerSoal[$jawaban->question_id][] = max(0, $ms - $sebelumnya);
                $sebelumnya = $ms;
            }
        }

        // Daya beda: selisih proporsi benar kelompok atas dan bawah (27%).
        $batas = (int) floor($attempts->count() * 0.27);
        $kelompokAtas = [];
        $kelompokBawah = [];
        if ($attempts->count() >= 6 && $batas >= 1) {
            $urut = $attempts->sortByDesc('skor_total')->values();
            $kelompokAtas = $urut->take($batas)->pluck('id')->all();
            $kelompokBawah = $urut->reverse()->take($batas)->pluck('id')->all();
        }

        return $questions->map(function (Question $question) use ($answers, $penerima, $benarPerSoal, $waktuPerSoal, $kelompokAtas, $kelompokBawah) {
            $penerimaSoal = $penerima->get($question->id, []);
            $peserta = count($penerimaSoal);
            $benar = (int) ($benarPerSoal[$question->id] ?? 0);
            $persen = $peserta > 0 ? round($benar / $peserta * 100, 1) : 0.0;
            $kategori = $persen < 30 ? 'Sukar' : ($persen <= 70 ? 'Sedang' : 'Mudah');

            $dayaBeda = null;
            if ($kelompokAtas !== [] && $kelompokBawah !== []) {
                $atas = count(array_intersect($penerimaSoal, $kelompokAtas));
                $bawah = count(array_intersect($penerimaSoal, $kelompokBawah));

                if ($atas > 0 && $bawah > 0) {
                    $jawabanSoal = $answers->where('question_id', $question->id)->where('is_correct', true);
                    $benarAtas = $jawabanSoal->whereIn('attempt_id', $kelompokAtas)->count();
                    $benarBawah = $jawabanSoal->whereIn('attempt_id', $kelompokBawah)->count();
                    $dayaBeda = round($benarAtas / $atas - $benarBawah / $bawah, 2);
                }
            }

            $waktu = $waktuPerSoal[$question->id] ?? [];

            return [
                'teks' => $question->teks,
                'peserta' => $peserta,
                'benar' => $benar,
                'persen' => $persen,
                'kategori' => $kategori,
                'daya_beda' => $dayaBeda,
                'rata_detik' => $waktu === [] ? null : (int) round(array_sum($waktu) / count($waktu) / 1000),
            ];
        })->sortBy('persen')->values()->all();
    }

    private function waktu(?int $ms): string
    {
        if ($ms === null) {
            return '';
        }

        return Carbon::createFromTimestampMs($ms, app(OrganizationContext::class)->timezone())->format('Y-m-d H:i:s');
    }

    private function slug(string $teks): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($teks)) ?: 'sesi';
    }
}
