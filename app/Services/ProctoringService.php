<?php

namespace App\Services;

use App\Events\PesertaPeringatan;
use App\Events\SessionMonitorUpdated;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\ParticipantLoginActivity;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProctoringService
{
    public function __construct(private QuizEngine $engine) {}

    /**
     * Catat satu kejadian. Satu sesi peringatan dihitung satu pelanggaran,
     * bukan satu kejadian, supaya blur dan tab hidden beruntun tidak
     * menggandakan hitungan.
     *
     * @return array{jumlah:int,maks:?int,durasi:int,batas_tercapai:bool}
     */
    public function catat(Attempt $attempt, string $tipe, ?string $detail, ?string $ip): array
    {
        $attempt->loadMissing(['user', 'quizSession']);
        $session = $attempt->quizSession;

        $kejadian = $attempt->events()->create([
            'tipe' => $tipe,
            'detail' => $detail,
            'ip' => $ip,
            'created_at' => now(),
        ]);

        if ($attempt->status === 'berjalan' && $attempt->peringatan_mulai_at === null) {
            $attempt->forceFill([
                'jumlah_pelanggaran' => (int) $attempt->jumlah_pelanggaran + 1,
                'peringatan_mulai_at' => now()->getTimestampMs(),
            ])->save();
        }

        $jumlah = (int) $attempt->jumlah_pelanggaran;
        $maks = $session?->max_pelanggaran;
        $durasi = $session?->durasiPeringatan() ?? 30;

        if ($session !== null) {
            SessionMonitorUpdated::safeDispatch($session->id, [
                'action' => 'violation',
                'user_id' => $attempt->user_id,
            ]);

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

        return [
            'jumlah' => $jumlah,
            'maks' => $maks !== null ? (int) $maks : null,
            'durasi' => $durasi,
            'batas_tercapai' => $maks !== null && $jumlah >= (int) $maks,
        ];
    }

    public function tutupPeringatan(Attempt $attempt): void
    {
        if ($attempt->peringatan_mulai_at !== null) {
            $attempt->forceFill(['peringatan_mulai_at' => null])->save();
        }
    }

    /**
     * Server tetap menjadi penentu akhir. Kalau client tidak sempat mengirim
     * timeout, polling status menyelesaikan attempt saat batas pelanggaran
     * sudah tercapai dan durasi peringatan habis.
     */
    public function periksaKedaluwarsa(Attempt $attempt): bool
    {
        if ($attempt->status !== 'berjalan' || $attempt->peringatan_mulai_at === null) {
            return false;
        }

        $attempt->loadMissing('quizSession');
        $batas = $attempt->peringatan_mulai_at + ($attempt->quizSession?->durasiPeringatan() ?? 30) * 1000;

        if (now()->getTimestampMs() < $batas) {
            return false;
        }

        $maks = $attempt->quizSession?->max_pelanggaran;

        if ($maks !== null && (int) $attempt->jumlah_pelanggaran >= (int) $maks) {
            $this->finalisasi($attempt);

            return true;
        }

        // Di bawah batas, sesi peringatan tetap terbuka sampai peserta kembali
        // atau mengirim timeout. Ini mencegah hitungan ganda dari polling dan
        // timer client yang tiba sedikit berbeda.
        return false;
    }

    public function finalisasi(Attempt $attempt): Attempt
    {
        $attempt->loadMissing('quizSession');

        if ($attempt->status !== 'berjalan' || $attempt->quizSession === null) {
            return $attempt;
        }

        $session = $attempt->quizSession;

        if ($session->mode === 'acak') {
            $attempt = $this->engine->selesaikanAcak($attempt);
        } else {
            $attempt = DB::transaction(function () use ($attempt, $session) {
                $attempt = Attempt::lockForUpdate()->findOrFail($attempt->id);

                if ($attempt->status !== 'berjalan') {
                    return $attempt;
                }

                $totalSoal = $session->sessionQuestions()->count();
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
                    'nilai' => $totalSoal > 0 ? (int) round($benar / $totalSoal * 100) : 0,
                ]);

                return $attempt;
            });

            app(NilaiService::class)->terapkanUntukSesi($session);
            $this->engine->bukaPembahasanOtomatis($session->fresh());
        }

        $this->engine->siarkanDisplay($session->fresh());

        SessionMonitorUpdated::safeDispatch($session->id, [
            'action' => 'attempt_selesai',
            'user_id' => $attempt->user_id,
        ]);

        return $attempt->fresh();
    }

    public function logout(Request $request, string $message): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            ParticipantLoginActivity::recordAndBroadcast($user, 'logout', 'fullscreen_violation');
            User::whereKey($user->id)->update(['current_session_token' => null]);
        }

        $organization = app(OrganizationContext::class)->current();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->to($organization ? route('organizations.login', $organization) : route('home'))
            ->with('peringatan', $message);
    }
}
