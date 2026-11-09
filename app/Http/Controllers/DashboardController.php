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

        $sesiAktif = QuizSession::with(['quiz', 'kelas'])
            ->withCount('attempts')
            ->whereIn('status', ['lobby', 'berjalan'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $sesiTerakhir = QuizSession::with(['quiz', 'kelas'])
            ->withCount('attempts')
            ->where('status', 'selesai')
            ->orderByDesc('ditutup_at')
            ->limit(5)
            ->get();

        $leaderboardTerbaru = Attempt::with(['user', 'quizSession.quiz'])
            ->where('status', 'selesai')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $loginHariIni = Attempt::where('created_at', '>=', now()->startOfDay())->count();
        $pesertaOnline = $this->getPesertaOnline();

        return view('admin.dashboard', compact('ringkasan', 'sesiAktif', 'sesiTerakhir', 'leaderboardTerbaru', 'loginHariIni', 'pesertaOnline'));
    }

    public function pesertaOnline(): JsonResponse
    {
        $peserta = $this->getPesertaOnline();

        return response()->json([
            'total' => count($peserta),
            'peserta' => $peserta,
        ]);
    }

    private function getPesertaOnline(): array
    {
        $threshold = now()->subMinutes(15)->timestamp;

        try {
            return \Illuminate\Support\Facades\DB::table('sessions')
                ->join('users', 'sessions.user_id', '=', 'users.id')
                ->where('users.role', 'peserta')
                ->where('sessions.last_activity', '>=', $threshold)
                ->select(
                    'users.id',
                    'users.nama',
                    'users.username',
                    \Illuminate\Support\Facades\DB::raw('MAX(sessions.last_activity) as last_activity')
                )
                ->groupBy('users.id', 'users.nama', 'users.username')
                ->orderByDesc('last_activity')
                ->limit(20)
                ->get()
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'nama' => $u->nama,
                    'username' => $u->username,
                    'inisial' => strtoupper(mb_substr($u->nama, 0, 1)),
                    'last_activity_ms' => (int) $u->last_activity * 1000,
                    'status' => 'online',
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function adminSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([
                'total' => 0,
                'sesi' => [],
                'kuis' => [],
                'peserta' => [],
                'kelas' => [],
            ]);
        }

        $sesi = QuizSession::with(['quiz', 'kelas'])
            ->where('nama', 'like', "%{$q}%")
            ->orWhereHas('quiz', fn ($query) => $query->where('judul', 'like', "%{$q}%"))
            ->orWhereHas('kelas', fn ($query) => $query->where('nama', 'like', "%{$q}%"))
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (QuizSession $s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'sub' => ($s->quiz->judul ?? '') . ($s->kelas ? ' · ' . $s->kelas->nama : ''),
                'status' => $s->status,
                'mode' => $s->mode,
                'url' => route('admin.sessions.show', $s),
            ]);

        $kuis = Quiz::with('kelas')
            ->withCount('questions')
            ->where('judul', 'like', "%{$q}%")
            ->orWhereHas('kelas', fn ($query) => $query->where('nama', 'like', "%{$q}%"))
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Quiz $k) => [
                'id' => $k->id,
                'judul' => $k->judul,
                'sub' => ($k->kelas ? $k->kelas->nama . ' · ' : '') . $k->questions_count . ' soal',
                'url' => route('admin.quizzes.questions.index', $k),
            ]);

        $peserta = User::where(function ($query) use ($q) {
                $query->where('nama', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN role = 'peserta' THEN 0 ELSE 1 END")
            ->orderBy('nama')
            ->limit(6)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'nama' => $u->nama,
                'sub' => $u->role === 'peserta' ? ('NIM/Username: ' . $u->username) : ('Administrator (' . $u->username . ')'),
                'role' => $u->role,
                'url' => $u->role === 'peserta'
                    ? route('admin.participants.index', ['q' => $u->username])
                    : route('admin.dashboard'),
            ]);

        $kelas = \App\Models\Kelas::where(function ($query) use ($q) {
                $query->where('nama', 'like', "%{$q}%")
                    ->orWhere('pin', 'like', "%{$q}%");
            })
            ->orderBy('nama')
            ->limit(4)
            ->get()
            ->map(fn (\App\Models\Kelas $c) => [
                'id' => $c->id,
                'nama' => $c->nama,
                'sub' => 'PIN: ' . $c->pin,
                'url' => route('admin.classes.index'),
            ]);

        $total = $sesi->count() + $kuis->count() + $peserta->count() + $kelas->count();

        return response()->json(compact('total', 'sesi', 'kuis', 'peserta', 'kelas'));
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

        $leaderboard = User::where('role', 'peserta')
            ->whereHas('attempts', fn ($q) => $q->where('status', 'selesai'))
            ->withSum(['attempts' => fn ($q) => $q->where('status', 'selesai')], 'skor_total')
            ->orderByDesc('attempts_sum_skor_total')
            ->limit(5)
            ->get();

        $stats = [
            'selesai' => $attempts->where('status', 'selesai')->count(),
            'total_skor' => (int) $attempts->where('status', 'selesai')->sum('skor_total'),
            'streak' => max(1, $attempts->where('status', 'selesai')->count()),
            'target_harian' => 3,
        ];

        $tourBaru = $user->tour_completed_at === null;

        return view('peserta.dashboard', compact('sesiTersedia', 'attempts', 'sesiList', 'kelasSaya', 'leaderboard', 'stats', 'tourBaru'));
    }

    public function selesaikanTour(Request $request): JsonResponse
    {
        $request->user()->forceFill(['tour_completed_at' => now()])->save();

        return response()->json(['ok' => true]);
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
