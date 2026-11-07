<?php

namespace App\Services;

use App\Events\DisplayUpdated;
use App\Events\SesiSelesai;
use App\Events\SesiStatusUpdated;
use App\Events\SoalDibuka;
use App\Events\SoalDitutup;
use App\Jobs\CloseQuestionJob;
use App\Jobs\NextQuestionJob;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuizSession;
use App\Models\SessionQuestion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizEngine
{
    public function gabung(QuizSession $session, User $user): Attempt
    {
        $attempt = DB::transaction(function () use ($session, $user) {
            $session = QuizSession::lockForUpdate()->findOrFail($session->id);

            $attempt = Attempt::where('user_id', $user->id)
                ->where('quiz_session_id', $session->id)
                ->first();

            if ($attempt !== null && $attempt->sudahBergabung()) {
                return $attempt;
            }

            if ($session->status === 'draft' || $session->status === 'selesai') {
                throw ValidationException::withMessages([
                    'gabung' => 'Sesi ini belum dibuka atau sudah selesai.',
                ]);
            }

            if ($session->kelas_id !== null
                && ! $session->kelas->peserta()->where('users.id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'gabung' => 'Anda belum bergabung ke kelas '.$session->kelas->nama.'. Masukkan PIN kelas di dashboard.',
                ]);
            }

            if (! $session->isUserAllowed($user)) {
                throw ValidationException::withMessages([
                    'gabung' => 'Anda tidak terdaftar sebagai peserta yang diizinkan untuk sesi ini.',
                ]);
            }

            if ($session->mode === 'serempak' && $session->status !== 'lobby') {
                throw ValidationException::withMessages([
                    'gabung' => 'Sesi Serempak sudah dimulai. Tunggu sesi berikutnya.',
                ]);
            }

            if ($attempt === null) {
                if ($session->isPenuh()) {
                    throw ValidationException::withMessages([
                        'gabung' => 'Kuota peserta sesi ini sudah penuh.',
                    ]);
                }

                $attempt = Attempt::create([
                    'user_id' => $user->id,
                    'quiz_session_id' => $session->id,
                    'status' => 'menunggu',
                ]);
            }

            $attempt->update(['bergabung_at' => now()]);

            return $attempt;
        });

        $fresh = $session->fresh();
        try {
            $this->siarkanDisplay($fresh);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast display error: ' . $e->getMessage());
        }
        SesiStatusUpdated::safeDispatch($fresh, ['action' => 'peserta_joined']);
        \App\Events\SessionMonitorUpdated::safeDispatch($session->id, ['action' => 'peserta_joined', 'user_id' => $user->id]);

        return $attempt;
    }

    public function mulaiAcak(Attempt $attempt): Attempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = Attempt::lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== 'menunggu') {
                return $attempt;
            }

            $session = $attempt->quizSession;

            if ($session->mode !== 'acak' || $session->status !== 'berjalan') {
                throw ValidationException::withMessages([
                    'attempt' => 'Sesi belum berjalan atau bukan Mode Acak.',
                ]);
            }

            $questions = $this->pilihSoal($session);

            if ($questions->isEmpty()) {
                throw ValidationException::withMessages([
                    'attempt' => 'Kuis ini belum punya soal.',
                ]);
            }

            $this->buatAttemptQuestions($attempt, $questions, $session->acak_opsi);

            $now = now()->getTimestampMs();
            $totalMs = (int) $questions->sum('durasi_detik') * 1000;

            $attempt->update([
                'status' => 'berjalan',
                'mulai_at' => $now,
                'batas_akhir_at' => $now + $totalMs,
            ]);

            return $attempt->fresh();
        });
    }

    public function selesaikanAcak(Attempt $attempt): Attempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = Attempt::lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status === 'selesai') {
                return $attempt;
            }

            if ($attempt->status !== 'berjalan') {
                return $attempt;
            }

            $this->hitungSkorAcak($attempt);

            $attempt->update([
                'status' => 'selesai',
                'selesai_at' => now()->getTimestampMs(),
            ]);

            return $attempt->fresh();
        });
    }

    /**
     * Hitung poin Mode Acak dari bobot soal dan jawaban tersimpan.
     * PG otomatis, essay memakai skor yang sudah dinilai admin.
     */
    public function hitungSkorAcak(Attempt $attempt): Attempt
    {
        $attempt->loadMissing('attemptQuestions.question');

        $answers = $attempt->answers()->with('option')->get()->keyBy('question_id');
        $poin = 0;
        $maksimal = 0;
        $benar = 0;

        foreach ($attempt->attemptQuestions as $attemptQuestion) {
            $question = $attemptQuestion->question;

            if ($question === null) {
                continue;
            }

            $bobot = max(1, (int) $question->bobot);
            $maksimal += $bobot;
            $jawaban = $answers->get($question->id);

            if ($question->isEssay()) {
                $poin += max(0, min($bobot, (int) ($jawaban?->skor ?? 0)));

                continue;
            }

            $tepat = $jawaban?->option?->is_correct === true;

            if ($tepat) {
                $poin += $bobot;
                $benar++;
            }

            if ($jawaban !== null && $jawaban->skor !== ($tepat ? $bobot : 0)) {
                $jawaban->update(['skor' => $tepat ? $bobot : 0]);
            }
        }

        $attempt->update([
            'jumlah_benar' => $benar,
            'poin_total' => $poin,
            'poin_maksimal' => $maksimal,
            'skor_total' => $maksimal > 0 ? (int) round($poin / $maksimal * 100) : 0,
        ]);

        return $attempt->fresh();
    }

    /**
     * Kumpulkan pekerjaan peserta atas perintah admin, tanpa menunggu
     * peserta menekan tombol Selesai. Skor dihitung dari jawaban tersimpan.
     */
    public function kumpulkanPaksa(Attempt $attempt): Attempt
    {
        $attempt->loadMissing('quizSession');

        if ($attempt->quizSession->mode === 'acak') {
            return $this->selesaikanAcak($attempt);
        }

        return DB::transaction(function () use ($attempt) {
            $attempt = Attempt::lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status === 'selesai') {
                return $attempt;
            }

            $this->hitungUlangSkorSerempak($attempt->id);

            $attempt->update([
                'status' => 'selesai',
                'selesai_at' => now()->getTimestampMs(),
            ]);

            return $attempt->fresh();
        });
    }

    public function tutupAttemptKedaluwarsa(): int
    {
        $ids = Attempt::query()
            ->where('status', 'berjalan')
            ->where('batas_akhir_at', '<', now()->getTimestampMs())
            ->whereHas('quizSession', fn ($q) => $q->where('mode', 'acak'))
            ->pluck('id');

        foreach ($ids as $id) {
            $this->selesaikanAcak(Attempt::findOrFail($id));
        }

        return $ids->count();
    }

    public function mulaiSesi(QuizSession $session): void
    {
        $dibuka = null;

        DB::transaction(function () use ($session, &$dibuka) {
            $session = QuizSession::lockForUpdate()->findOrFail($session->id);

            if ($session->status !== 'lobby') {
                throw ValidationException::withMessages([
                    'sesi' => 'Sesi hanya bisa dimulai dari lobby.',
                ]);
            }

            $questions = $this->pilihSoal($session);

            if ($questions->isEmpty()) {
                throw ValidationException::withMessages([
                    'sesi' => 'Kuis ini belum punya soal.',
                ]);
            }

            if ($session->mode === 'serempak' && $questions->contains(fn (Question $q) => $q->isEssay())) {
                throw ValidationException::withMessages([
                    'sesi' => 'Soal essay hanya didukung di Mode Acak.',
                ]);
            }

            $now = now()->getTimestampMs();
            $totalMs = (int) $questions->sum('durasi_detik') * 1000;

            if ($session->mode === 'serempak') {
                foreach ($questions->values() as $index => $question) {
                    SessionQuestion::create([
                        'quiz_session_id' => $session->id,
                        'question_id' => $question->id,
                        'urutan' => $index + 1,
                    ]);
                }

                $attempts = Attempt::where('quiz_session_id', $session->id)
                    ->bergabung()
                    ->get();

                foreach ($attempts as $attempt) {
                    $this->buatAttemptQuestions($attempt, $questions, $session->acak_opsi);

                    $attempt->update([
                        'status' => 'berjalan',
                        'mulai_at' => $now,
                        'batas_akhir_at' => $now + $totalMs,
                    ]);
                }
            }

            $session->update([
                'status' => 'berjalan',
                'fase' => null,
            ]);

            if ($session->mode === 'serempak') {
                $pertama = $session->sessionQuestions()->first();
                $dibuka = $this->bukaSoal($session, $pertama);
            }
        });

        if ($dibuka !== null) {
            $this->siarkanSoalDibuka($dibuka);
        }

        $fresh = $session->fresh();
        try {
            $this->siarkanDisplay($fresh);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast display error: ' . $e->getMessage());
        }
        SesiStatusUpdated::safeDispatch($fresh);
    }

    public function tutupSoal(QuizSession $session, int $urutan): void
    {
        $hasil = null;

        DB::transaction(function () use ($session, $urutan, &$hasil) {
            $session = QuizSession::lockForUpdate()->findOrFail($session->id);

            if ($session->status !== 'berjalan' || $session->fase !== 'soal_terbuka' || $session->soal_aktif_urutan !== $urutan) {
                return;
            }

            $sessionQuestion = $session->sessionQuestions()
                ->where('urutan', $urutan)
                ->with('question.options')
                ->first();

            if ($sessionQuestion === null) {
                return;
            }

            $question = $sessionQuestion->question;
            $benarOption = $question->options->firstWhere('is_correct', true);

            $answers = Answer::query()
                ->where('question_id', $question->id)
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->with('option')
                ->get();

            $attemptIds = $answers->pluck('attempt_id')->unique();

            foreach ($answers as $answer) {
                $correct = $answer->option !== null && $answer->option->is_correct;

                $skor = 0;
                if ($correct) {
                    $durasiMs = max(1, $question->durasi_detik * 1000);
                    $rasio = min(1, max(0, (float) $answer->waktu_jawab_ms / $durasiMs));
                    $skor = (int) round(1000 * (1 - $rasio / 2));
                }

                $answer->update(['skor' => $skor]);
            }

            foreach ($attemptIds as $attemptId) {
                $this->hitungUlangSkorSerempak($attemptId);
            }

            $distribusi = $answers
                ->groupBy(fn (Answer $a) => $a->option?->teks ?? 'Tidak dijawab')
                ->map(fn (Collection $group, string $teks) => [
                    'teks' => $teks,
                    'jumlah' => $group->count(),
                ])
                ->values()
                ->all();

            $top5 = $this->leaderboard($session, 5);

            $session->update([
                'fase' => 'jeda',
                'soal_berakhir_at' => now()->getTimestampMs(),
            ]);

            $hasil = [
                'session_id' => $session->id,
                'urutan' => $urutan,
                'total' => $session->sessionQuestions()->count(),
                'jawaban_benar' => $benarOption?->teks,
                'pembahasan' => $session->tampilkan_pembahasan ? $question->pembahasan : null,
                'distribusi' => $distribusi,
                'top5' => $session->leaderboard_live ? $top5 : [],
                'jeda_detik' => $session->jeda_detik,
                'server_now' => now()->getTimestampMs(),
            ];
        });

        if ($hasil === null) {
            return;
        }

        SoalDitutup::dispatch($session->id, $hasil);

        $fresh = $session->fresh();
        $this->siarkanDisplay($fresh);

        NextQuestionJob::dispatch($fresh->id, $urutan)->delay(now()->addSeconds(max(1, $fresh->jeda_detik)));
    }

    public function lanjutSoal(QuizSession $session, int $urutan): void
    {
        $dibuka = null;

        DB::transaction(function () use ($session, $urutan, &$dibuka) {
            $session = QuizSession::lockForUpdate()->findOrFail($session->id);

            if ($session->status !== 'berjalan' || $session->fase !== 'jeda' || $session->soal_aktif_urutan !== $urutan) {
                return;
            }

            $berikut = $session->sessionQuestions()
                ->where('urutan', '>', $urutan)
                ->first();

            if ($berikut === null) {
                $this->tutupSesiTerkunci($session);

                return;
            }

            $dibuka = $this->bukaSoal($session, $berikut);
        });

        $fresh = $session->fresh();

        if ($dibuka !== null) {
            $this->siarkanSoalDibuka($dibuka);
        }

        $this->siarkanDisplay($fresh);

        if ($fresh->status === 'selesai') {
            try {
                SesiSelesai::dispatch($fresh->id, ['server_now' => now()->getTimestampMs()]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Broadcast sesi selesai error: ' . $e->getMessage());
            }
            SesiStatusUpdated::safeDispatch($fresh);
        }
    }

    public function tutupSesi(QuizSession $session): void
    {
        $sekarang = $session->fresh();

        if ($sekarang->mode === 'serempak'
            && $sekarang->status === 'berjalan'
            && $sekarang->fase === 'soal_terbuka'
            && $sekarang->soal_aktif_urutan !== null) {
            $this->tutupSoal($sekarang, $sekarang->soal_aktif_urutan);
        }

        DB::transaction(function () use ($session) {
            $session = QuizSession::lockForUpdate()->findOrFail($session->id);

            $this->tutupSesiTerkunci($session);
        });

        $fresh = $session->fresh();
        try {
            $this->siarkanDisplay($fresh);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast display error: ' . $e->getMessage());
        }
        try {
            SesiSelesai::dispatch($fresh->id, ['server_now' => now()->getTimestampMs()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast sesi selesai error: ' . $e->getMessage());
        }
        SesiStatusUpdated::safeDispatch($fresh);
    }

    private function tutupSesiTerkunci(QuizSession $session): void
    {
        if ($session->status === 'selesai') {
            return;
        }

        $attempts = Attempt::where('quiz_session_id', $session->id)->get();

        foreach ($attempts as $attempt) {
            if ($attempt->status === 'selesai') {
                continue;
            }

            // Peserta yang tidak pernah menekan Gabung tidak ikut ditutup.
            // Attempt-nya tetap ada sebagai catatan daftar hadir dari admin.
            if (! $attempt->sudahBergabung()) {
                continue;
            }

            if ($attempt->status === 'berjalan' && $session->mode === 'acak') {
                $this->hitungSelesaiAcakTerkunci($attempt);
            } else {
                $attempt->update([
                    'status' => 'selesai',
                    'selesai_at' => now()->getTimestampMs(),
                ]);
            }
        }

        $session->update([
            'status' => 'selesai',
            'fase' => null,
            'ditutup_at' => now(),
        ]);
    }

    private function hitungSelesaiAcakTerkunci(Attempt $attempt): void
    {
        $this->hitungSkorAcak($attempt);

        $attempt->update([
            'status' => 'selesai',
            'selesai_at' => now()->getTimestampMs(),
        ]);
    }

    private function bukaSoal(QuizSession $session, SessionQuestion $sessionQuestion): array
    {
        $durasiMs = max(1, $sessionQuestion->question->durasi_detik * 1000);
        $now = now()->getTimestampMs();
        $berakhir = $now + $durasiMs;

        $session->update([
            'fase' => 'soal_terbuka',
            'soal_aktif_urutan' => $sessionQuestion->urutan,
            'soal_mulai_at' => $now,
            'soal_berakhir_at' => $berakhir,
        ]);

        CloseQuestionJob::dispatch($session->id, $sessionQuestion->urutan)
            ->delay(now()->addMilliseconds($durasiMs));

        return [
            'session_id' => $session->id,
            'urutan' => $sessionQuestion->urutan,
            'total' => $session->sessionQuestions()->count(),
            'server_now' => $now,
            'ends_at' => $berakhir,
            'durasi_detik' => $sessionQuestion->question->durasi_detik,
        ];
    }

    private function siarkanSoalDibuka(array $payload): void
    {
        try {
            SoalDibuka::dispatch($payload['session_id'], $payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast soal dibuka error: ' . $e->getMessage());
        }
    }

    private function hitungUlangSkorSerempak(int $attemptId): void
    {
        $attempt = Attempt::find($attemptId);

        if ($attempt === null) {
            return;
        }

        $answers = Answer::where('attempt_id', $attemptId)->get();

        $benar = Answer::query()
            ->where('answers.attempt_id', $attemptId)
            ->join('options', 'options.id', '=', 'answers.option_id')
            ->where('options.is_correct', true)
            ->count();

        $attempt->update([
            'jumlah_benar' => $benar,
            'skor_total' => (int) $answers->sum('skor'),
        ]);
    }

    /**
     * @return Collection<int, Question>
     */
    public function pilihSoal(QuizSession $session): Collection
    {
        $questions = $session->quiz->questions()->get()->shuffle();

        if ($session->quiz->jumlah_soal !== null) {
            $questions = $questions->take($session->quiz->jumlah_soal);
        }

        return $questions->values();
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function buatAttemptQuestions(Attempt $attempt, Collection $questions, bool $acakOpsi): void
    {
        foreach ($questions->values() as $index => $question) {
            $optionIds = $question->options()->pluck('id')->all();

            if ($acakOpsi) {
                shuffle($optionIds);
            }

            AttemptQuestion::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'urutan' => $index + 1,
                'urutan_opsi_json' => $optionIds,
            ]);
        }
    }

    public function leaderboard(QuizSession $session, int $limit = 5): array
    {
        return $session->attempts()
            ->bergabung()
            ->when($session->mode === 'acak', fn ($q) => $q->where('status', 'selesai'))
            ->when($session->mode === 'serempak', fn ($q) => $q->where('status', '!=', 'menunggu'))
            ->orderByDesc('skor_total')
            ->orderBy('selesai_at')
            ->limit($limit)
            ->with('user')
            ->get()
            ->values()
            ->map(fn (Attempt $a, int $i) => [
                'posisi' => $i + 1,
                'nama' => $a->user->nama,
                'skor' => $a->skor_total,
            ])
            ->all();
    }

    public function siarkanDisplay(QuizSession $session): void
    {
        try {
            DisplayUpdated::dispatch($session->display_token, $this->displayState($session));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast display error: ' . $e->getMessage());
        }
    }

    public function siarkanDisplayTerbatas(QuizSession $session): void
    {
        if (\Illuminate\Support\Facades\Cache::add('display-progress-'.$session->id, 1, 1)) {
            $this->siarkanDisplay($session);
        }
    }

    public function displayState(QuizSession $session): array
    {
        $session->loadMissing('quiz');

        $base = [
            'nama' => $session->nama,
            'kuis' => $session->quiz->judul,
            'mode' => $session->mode,
            'status' => $session->status,
            'server_now' => now()->getTimestampMs(),
            'total_peserta' => $session->attempts()->bergabung()->count(),
            'peringatan' => $this->peringatanTerbaru($session),
        ];

        if ($session->status === 'lobby' || $session->status === 'draft') {
            return $base + [
                'peserta' => $session->attempts()->bergabung()->with('user')->get()->pluck('user.nama')->all(),
            ];
        }

        if ($session->status === 'selesai') {
            return $base + $this->hasilAkhir($session);
        }

        if ($session->mode === 'serempak') {
            return $base + $this->statusSerempak($session);
        }

        $attempts = $session->attempts()->bergabung()->get();
        $selesai = $attempts->where('status', 'selesai')->count();
        $terjawab = Answer::whereIn('attempt_id', $attempts->pluck('id'))->count();

        return $base + [
            'fase' => null,
            'total' => $attempts->count(),
            'selesai' => $selesai,
            'rata_terjawab' => $attempts->count() > 0 ? round($terjawab / $attempts->count(), 1) : 0,
            'leaderboard_live' => (bool) $session->leaderboard_live,
            'top5' => $session->leaderboard_live ? $this->leaderboard($session) : [],
        ];
    }

    /**
     * Kejadian terbaru untuk notifikasi layar. Dipakai juga oleh polling
     * 3 detik, sehingga peringatan tetap tampil saat websocket terputus.
     *
     * @return array<int, array<string, mixed>>
     */
    private function peringatanTerbaru(QuizSession $session): array
    {
        return AttemptEvent::query()
            ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
            ->where('created_at', '>=', now()->subMinutes(5))
            ->with(['attempt' => fn ($q) => $q->with('user')->withCount('events')])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->reverse()
            ->values()
            ->map(function (AttemptEvent $kejadian) {
                $label = AttemptEvent::label($kejadian->tipe);

                return [
                    'id' => $kejadian->id,
                    'nama' => $kejadian->attempt?->user?->nama ?? 'Peserta',
                    'username' => $kejadian->attempt?->user?->username ?? '-',
                    'tipe' => $kejadian->tipe,
                    'pesan' => $label['pesan'],
                    'level' => $label['level'],
                    'waktu' => $kejadian->created_at?->format('H:i:s'),
                    'jumlah_pelanggaran' => $kejadian->attempt?->events_count ?? 1,
                ];
            })
            ->all();
    }

    private function statusSerempak(QuizSession $session): array
    {
        $totalSoal = $session->sessionQuestions()->count();
        $urutan = $session->soal_aktif_urutan;
        $sessionQuestion = $session->sessionQuestions()->where('urutan', $urutan)->with('question.options')->first();

        $skor = $session->attempts()->bergabung()->count();

        $data = [
            'fase' => $session->fase,
            'urutan' => $urutan,
            'total_soal' => $totalSoal,
            'total' => $skor,
            'ends_at' => $session->soal_berakhir_at,
            'jeda_detik' => $session->jeda_detik,
            'leaderboard_live' => (bool) $session->leaderboard_live,
        ];

        if ($sessionQuestion !== null) {
            $question = $sessionQuestion->question;
            $answers = Answer::where('question_id', $question->id)
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->with('option')
                ->get();

            $data['sudah_menjawab'] = $answers->count();

            if ($session->fase === 'jeda') {
                $data['distribusi'] = $answers
                    ->groupBy(fn (Answer $a) => $a->option?->teks ?? 'Tidak dijawab')
                    ->map(fn (Collection $group, string $teks) => ['teks' => $teks, 'jumlah' => $group->count()])
                    ->values()
                    ->all();
                $data['jawaban_benar'] = $question->options->firstWhere('is_correct', true)?->teks;
                $data['top5'] = $session->leaderboard_live ? $this->leaderboard($session) : [];
            }
        }

        return $data;
    }

    private function hasilAkhir(QuizSession $session): array
    {
        $attempts = $session->attempts()->bergabung()->with('user')->get();
        $totalSoal = $session->mode === 'serempak'
            ? max(1, $session->sessionQuestions()->count())
            : max(1, (int) $attempts->max(fn (Attempt $a) => $a->attemptQuestions()->count()));

        $persen = function (Attempt $a) use ($session, $totalSoal): float {
            if ($session->mode === 'serempak') {
                return round($a->skor_total / ($totalSoal * 1000) * 100, 1);
            }

            return (float) $a->skor_total;
        };

        $histogram = array_fill(0, 11, 0);

        foreach ($attempts as $attempt) {
            $bucket = (int) min(10, floor($persen($attempt) / 10));
            $histogram[$bucket]++;
        }

        $soalIds = $session->mode === 'serempak'
            ? $session->sessionQuestions()->pluck('question_id')->all()
            : AttemptQuestion::query()
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->distinct()
                ->pluck('question_id')
                ->all();

        $soalTerendah = Question::withTrashed()
            ->with('options')
            ->whereIn('id', $soalIds)
            ->where('tipe', 'pilihan_ganda')
            ->get()
            ->map(function (Question $question) use ($session) {
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

                $persen = $peserta > 0 ? round($benar / $peserta * 100, 1) : 0;

                return ['teks' => $question->teks, 'persen' => $persen];
            })
            ->sortBy('persen')
            ->take(5)
            ->values()
            ->all();

        return [
            'leaderboard' => $attempts
                ->sortBy([['skor_total', 'desc'], ['selesai_at', 'asc']])
                ->values()
                ->map(fn (Attempt $a, int $i) => [
                    'posisi' => $i + 1,
                    'nama' => $a->user->nama,
                    'skor' => $a->skor_total,
                    'persen' => $persen($a),
                ])
                ->all(),
            'histogram' => $histogram,
            'soal_terendah' => $soalTerendah,
        ];
    }

    public function soalAktifUntukAttempt(QuizSession $session, Attempt $attempt): ?array
    {
        $urutan = $session->soal_aktif_urutan;

        if ($urutan === null) {
            return null;
        }

        $attemptQuestion = $attempt->attemptQuestions()
            ->where('urutan', $urutan)
            ->with('question.options')
            ->first();

        if ($attemptQuestion === null) {
            return null;
        }

        $question = $attemptQuestion->question;
        $order = $attemptQuestion->urutan_opsi_json;

        $options = collect($order)
            ->map(fn ($id) => $question->options->firstWhere('id', (int) $id))
            ->filter()
            ->values();

        $answer = Answer::where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        return [
            'id' => $question->id,
            'teks' => $question->teks,
            'tipe' => $question->tipe,
            'bobot' => $question->bobot,
            'durasi_detik' => $question->durasi_detik,
            'opsi' => $options->map(fn (Option $o) => ['id' => $o->id, 'teks' => $o->teks])->all(),
            'jawaban_option_id' => $answer?->option_id,
            'urutan' => $urutan,
            'total_soal' => $session->sessionQuestions()->count(),
        ];
    }
}
