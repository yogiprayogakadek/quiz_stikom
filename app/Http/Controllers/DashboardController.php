<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function admin(): View
    {
        $ringkasan = [
            'peserta' => User::where('role', 'peserta')->count(),
            'soal' => Question::count(),
            'kuis' => Quiz::count(),
            'sesi_aktif' => QuizSession::whereIn('status', ['lobby', 'berjalan'])->count(),
        ];

        $sesiAktif = QuizSession::with('quiz')
            ->withCount('attempts')
            ->whereIn('status', ['lobby', 'berjalan'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $sesiTerakhir = QuizSession::with('quiz')
            ->withCount('attempts')
            ->where('status', 'selesai')
            ->orderByDesc('ditutup_at')
            ->limit(5)
            ->get();

        $loginHariIni = Attempt::where('created_at', '>=', now()->startOfDay())->count();

        return view('admin.dashboard', compact('ringkasan', 'sesiAktif', 'sesiTerakhir', 'loginHariIni'));
    }

    public function peserta(Request $request): View
    {
        $user = $request->user();

        $kelasSaya = $user->kelas()->withCount(['quizzes', 'sessions'])->orderBy('nama')->get();

        $sesiTersedia = QuizSession::with(['kelas', 'quiz' => fn ($q) => $q->withCount('questions')])
            ->withCount('attempts')
            ->whereIn('status', ['lobby', 'berjalan'])
            ->untukPeserta($user)
            ->where(function ($query) use ($user) {
                $query->whereDoesntHave('allowedParticipants')
                    ->orWhereHas('allowedParticipants', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderByRaw("CASE status WHEN 'berjalan' THEN 0 ELSE 1 END")
            ->orderByDesc('dibuka_at')
            ->get();

        $attempts = Attempt::with('quizSession.quiz')
            ->where('user_id', $user->id)
            ->whereNotNull('bergabung_at')
            ->orderByDesc('updated_at')
            ->get();

        $sesiList = $this->formatSesi($sesiTersedia, $attempts);

        return view('peserta.dashboard', compact('sesiTersedia', 'attempts', 'sesiList', 'kelasSaya'));
    }

    public function pesertaState(Request $request): JsonResponse
    {
        $user = $request->user();

        $sesiTersedia = QuizSession::with(['kelas', 'quiz' => fn ($q) => $q->withCount('questions')])
            ->withCount('attempts')
            ->whereIn('status', ['lobby', 'berjalan'])
            ->untukPeserta($user)
            ->where(function ($query) use ($user) {
                $query->whereDoesntHave('allowedParticipants')
                    ->orWhereHas('allowedParticipants', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderByRaw("CASE status WHEN 'berjalan' THEN 0 ELSE 1 END")
            ->orderByDesc('dibuka_at')
            ->get();

        $attempts = Attempt::with('quizSession.quiz')
            ->where('user_id', $user->id)
            ->whereNotNull('bergabung_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'sesi_tersedia' => $this->formatSesi($sesiTersedia, $attempts),
            'server_now' => now()->getTimestampMs(),
        ]);
    }

    private function formatSesi($sesiTersedia, $attempts): array
    {
        return $sesiTersedia->map(function (QuizSession $sesi) use ($attempts) {
            $attempt = $attempts->firstWhere('quiz_session_id', $sesi->id);
            $penuh = $sesi->max_peserta !== null && $sesi->attempts_count >= $sesi->max_peserta;
            $jumlahSoal = $sesi->quiz->jumlah_soal ?? $sesi->quiz->questions_count;

            return [
                'id' => $sesi->id,
                'nama' => $sesi->nama,
                'kelas_nama' => $sesi->kelas?->nama,
                'mode' => $sesi->mode,
                'status' => $sesi->status,
                'quiz_judul' => $sesi->quiz->judul,
                'jumlah_soal' => $jumlahSoal,
                'attempts_count' => $sesi->attempts_count,
                'max_peserta' => $sesi->max_peserta,
                'penuh' => $penuh,
                'attempt' => $attempt ? [
                    'id' => $attempt->id,
                    'status' => $attempt->status,
                ] : null,
                'urls' => [
                    'join' => route('peserta.session.join', $sesi),
                    'start' => route('peserta.attempt.start', $sesi),
                    'play' => route('peserta.session.play', $sesi),
                    'show' => $attempt ? route('peserta.attempt.show', $attempt) : null,
                    'result' => $attempt ? ($sesi->mode === 'acak' ? route('peserta.attempt.result', $attempt) : route('peserta.session.result', $sesi)) : null,
                ],
            ];
        })->values()->all();
    }
}
