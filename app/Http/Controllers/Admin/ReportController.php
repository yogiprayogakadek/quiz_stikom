<?php

namespace App\Http\Controllers\Admin;

use App\Events\EssayDinilai;
use App\Events\SessionMonitorUpdated;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuizSession;
use App\Services\EssayGrader;
use App\Services\NilaiService;
use App\Services\QuizEngine;
use App\Support\OrganizationContext;
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
        $session->load('quiz', 'remidi');

        $tinjau = $request->boolean('tinjau');

        $attempts = $session->attempts()
            ->mengikuti()
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

        $totalAttempt = $session->attempts()->mengikuti()->count();
        $selesai = $session->attempts()->mengikuti()->where('status', 'selesai')->count();
        $perluTinjau = $session->attempts()->mengikuti()->has('events', '>=', 3)->count();

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

    public function attempt(Attempt $attempt): View
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

            return [
                'question_id' => $question->id,
                'answer_id' => $answer?->id,
                'urutan' => $aq->urutan,
                'tipe' => $question->tipe,
                'bobot' => $question->bobot,
                'teks' => $question->teks,
                'jawaban_saya' => $essay ? $answer?->jawaban_teks : $answer?->option?->teks,
                'jawaban_benar' => $essay ? null : $question->correctOption()?->teks,
                'kunci_essay' => $essay ? $question->kunci_essay : null,
                'rubrik_essay' => $essay ? ($question->rubrik_essay ?? []) : [],
                'benar' => $essay ? null : ($answer?->option?->is_correct === true),
                'dinilai_at' => $answer?->dinilai_at,
                'skor' => $answer?->skor ?? 0,
                'ai_suggested_score' => $answer?->ai_suggested_score,
                'ai_confidence' => $answer?->ai_confidence,
                'ai_feedback' => $answer?->ai_feedback,
                'ai_reviewed_at' => $answer?->ai_reviewed_at,
                'history' => $answer ? ($history->get($answer->id) ?? collect()) : collect(),
            ];
        });

        return view('admin.reports.attempt', compact('attempt', 'rincian'));
    }

    public function nilaiEssay(Request $request, Attempt $attempt, QuizEngine $engine, EssayGrader $grader): RedirectResponse
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

            $grader->applyFinal($answer, (int) $nilai, $request->user()->id);
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

        Log::notice('Admin menilai jawaban essay.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'attempt_id' => $attempt->id,
            'nilai' => $data['nilai'],
        ]);

        return back()->with('sukses', 'Nilai essay disimpan dan skor attempt diperbarui.');
    }

    public function saranNilaiEssay(Answer $answer, EssayGrader $grader): RedirectResponse
    {
        $answer->loadMissing(['question', 'attempt.quizSession']);
        abort_unless($answer->attempt?->quizSession && $answer->question?->isEssay(), 404);
        abort_unless($answer->attempt->status === 'selesai', 422);
        abort_unless(filled($answer->jawaban_teks), 422);

        try {
            $grader->suggest($answer);
        } catch (\Throwable $e) {
            return back()->withErrors(['ai_essay' => $e->getMessage()]);
        }

        return back()->with('sukses', 'Saran AI dibuat. Periksa hasilnya sebelum menyimpan nilai final.');
    }

    public function export(QuizSession $session): StreamedResponse
    {
        $attempts = $session->attempts()
            ->mengikuti()
            ->with('user')
            ->withCount([
                'events',
                'answers as essay_belum_dinilai' => fn ($q) => $q
                    ->whereHas('question', fn ($qq) => $qq->where('tipe', 'essay'))
                    ->whereNull('dinilai_at'),
            ])
            ->withSum(['events as detik_luar' => fn ($q) => $q->whereNotNull('durasi_detik')], 'durasi_detik')
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

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
