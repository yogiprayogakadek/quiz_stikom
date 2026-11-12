<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\ParticipantLoginActivity;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function admin(): View
    {
        $ringkasan = [
            'peserta' => User::where('role', 'peserta')->inCurrentOrganization()->count(),
            'soal' => Question::whereHas('quiz')->count(),
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
            ->whereHas('quizSession')
            ->whereHas('quizSession.quiz', fn ($q) => $q->where('is_percobaan', false))
            ->where('status', 'selesai')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->sortByDesc('skor_total')
            ->values();

        $loginHariIni = Attempt::whereHas('quizSession')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        $pesertaOnline = $this->getPesertaOnline();
        $aktivitasLogin = $this->getAktivitasLogin();

        return view('admin.dashboard', compact('ringkasan', 'sesiAktif', 'sesiTerakhir', 'leaderboardTerbaru', 'loginHariIni', 'pesertaOnline', 'aktivitasLogin'));
    }

    public function pesertaOnline(): JsonResponse
    {
        $peserta = $this->getPesertaOnline();

        return response()->json([
            'total' => count($peserta),
            'peserta' => $peserta,
            'activities' => $this->getAktivitasLogin(),
            'server_now' => now()->getTimestampMs(),
        ]);
    }

    private function getPesertaOnline(): array
    {
        $threshold = now()->subMinutes(15)->timestamp;

        try {
            $organizationId = app(OrganizationContext::class)->id();
            $query = DB::table('sessions')
                ->join('users', 'sessions.user_id', '=', 'users.id')
                ->where('users.role', 'peserta')
                ->whereNotNull('users.current_session_token')
                ->where('sessions.last_activity', '>=', $threshold);

            if ($organizationId !== null) {
                $query->join('organization_memberships', 'users.id', '=', 'organization_memberships.user_id')
                    ->where('organization_memberships.organization_id', $organizationId)
                    ->where('organization_memberships.status', 'active');
            }

            return $query
                ->select(
                    'users.id',
                    'users.nama',
                    'users.username',
                    DB::raw('MAX(sessions.last_activity) as last_activity')
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

    private function getAktivitasLogin(): array
    {
        return ParticipantLoginActivity::query()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ParticipantLoginActivity $activity) => [
                'id' => $activity->id,
                'user_id' => $activity->user_id,
                'nama' => $activity->nama,
                'username' => $activity->username,
                'status' => $activity->status,
                'reason' => $activity->reason,
                'waktu' => $activity->created_at->toISOString(),
            ])
            ->all();
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
                'sub' => ($s->quiz->judul ?? '').($s->kelas ? ' · '.$s->kelas->nama : ''),
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
                'sub' => ($k->kelas ? $k->kelas->nama.' · ' : '').$k->questions_count.' soal',
                'url' => route('admin.quizzes.questions.index', $k),
            ]);

        $peserta = User::where(function ($query) use ($q) {
            $query->where('nama', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%");
        })->inCurrentOrganization()
            ->orderByRaw("CASE WHEN role = 'peserta' THEN 0 ELSE 1 END")
            ->orderBy('nama')
            ->limit(6)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'nama' => $u->nama,
                'sub' => $u->role === 'peserta' ? ('NIM/Username: '.$u->username) : ('Administrator ('.$u->username.')'),
                'role' => $u->role,
                'url' => $u->role === 'peserta'
                    ? route('admin.participants.index', ['q' => $u->username])
                    : route('admin.dashboard'),
            ]);

        $kelas = Kelas::where(function ($query) use ($q) {
            $query->where('nama', 'like', "%{$q}%")
                ->orWhere('pin', 'like', "%{$q}%");
        })
            ->orderBy('nama')
            ->limit(4)
            ->get()
            ->map(fn (Kelas $c) => [
                'id' => $c->id,
                'nama' => $c->nama,
                'sub' => 'PIN: '.$c->pin,
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

        $organizationId = app(OrganizationContext::class)->id();

        $nilaiTerbaik = DB::table('attempts')
            ->join('quiz_sessions', 'quiz_sessions.id', '=', 'attempts.quiz_session_id')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_sessions.quiz_id')
            ->leftJoin('quiz_sessions as sesi_awal', 'sesi_awal.id', '=', 'quiz_sessions.remidi_dari_session_id')
            ->leftJoin('quizzes as kuis_awal', 'kuis_awal.id', '=', 'sesi_awal.quiz_id')
            ->where('attempts.status', 'selesai')
            ->whereNotNull('attempts.bergabung_at')
            ->whereNotNull('attempts.nilai')
            ->whereRaw('COALESCE(kuis_awal.is_percobaan, quizzes.is_percobaan) = ?', [false])
            ->when($organizationId !== null, fn ($q) => $q->where('attempts.organization_id', $organizationId))
            ->groupBy('attempts.user_id', DB::raw('COALESCE(kuis_awal.id, quizzes.id)'))
            ->selectRaw('attempts.user_id, MAX(attempts.nilai) as nilai')
            ->get();

        $totalPerPeserta = $nilaiTerbaik
            ->groupBy('user_id')
            ->map(fn ($rows) => (int) $rows->sum('nilai'));

        $leaderboard = User::where('role', 'peserta')
            ->inCurrentOrganization()
            ->whereIn('id', $totalPerPeserta->keys())
            ->get()
            ->each(fn (User $peserta) => $peserta->attempts_sum_skor_total = $totalPerPeserta->get($peserta->id, 0))
            ->sortByDesc('attempts_sum_skor_total')
            ->take(5)
            ->values();

        $stats = [
            'selesai' => $attempts->where('status', 'selesai')->count(),
            'total_skor' => (int) $totalPerPeserta->get($user->id, 0),
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
