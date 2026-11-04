<?php

namespace App\Http\Controllers\Admin;

use App\Events\SesiStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $sessions = QuizSession::with('quiz')
            ->withCount('attempts')
            ->when(in_array($status, ['draft', 'lobby', 'berjalan', 'selesai'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'berjalan' THEN 0 WHEN 'lobby' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.sessions.index', compact('sessions', 'status'));
    }

    public function create(): View
    {
        $quizzes = Quiz::withCount('questions')->orderBy('judul')->get();

        return view('admin.sessions.create', compact('quizzes'));
    }

    public function store(StoreSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $session = QuizSession::create([
            'quiz_id' => $data['quiz_id'],
            'nama' => $data['nama'],
            'mode' => $data['mode'],
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampilkan_pembahasan' => $request->boolean('tampilkan_pembahasan'),
            'leaderboard_live' => $request->boolean('leaderboard_live'),
            'max_peserta' => $data['max_peserta'] ?? null,
            'jeda_detik' => $data['jeda_detik'],
            'status' => 'draft',
            'display_token' => Str::random(48),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.sessions.show', $session)->with('sukses', 'Sesi dibuat. Buka lobby saat peserta siap bergabung.');
    }

    public function show(QuizSession $session): View
    {
        $session->load('quiz');

        $attempts = $session->attempts()
            ->with('user')
            ->withCount('events')
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        $soalAktif = $session->soalAktif();

        if ($soalAktif !== null) {
            $soalAktif->load('question');
        }

        $sudahMenjawab = $session->soal_aktif_urutan !== null && $soalAktif !== null
            ? \App\Models\Answer::query()
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->where('question_id', $soalAktif->question_id)
                ->count()
            : 0;

        $totalPeserta = $attempts->count();

        $allParticipants = User::where('role', 'peserta')
            ->orderBy('nama')
            ->get();

        $allowedParticipantIds = $session->allowedParticipants()->pluck('users.id')->all();

        // Jika belum ada filter peserta khusus yang disimpan, tandai semua peserta terdaftar secara default
        if (empty($allowedParticipantIds)) {
            $allowedParticipantIds = $allParticipants->pluck('id')->all();
        }

        return view('admin.sessions.show', compact(
            'session',
            'attempts',
            'soalAktif',
            'sudahMenjawab',
            'totalPeserta',
            'allParticipants',
            'allowedParticipantIds'
        ));
    }

    public function edit(QuizSession $session): View
    {
        $quizzes = Quiz::withCount('questions')->orderBy('judul')->get();

        return view('admin.sessions.edit', compact('session', 'quizzes'));
    }

    public function update(UpdateSessionRequest $request, QuizSession $session): RedirectResponse
    {
        $data = $request->validated();

        if ($session->status !== 'draft') {
            if ((int) $data['quiz_id'] !== $session->quiz_id || $data['mode'] !== $session->mode) {
                throw ValidationException::withMessages([
                    'mode' => 'Mode dan kuis terkunci karena sesi sudah keluar dari status draft.',
                ]);
            }
        }

        $session->update([
            'quiz_id' => $data['quiz_id'],
            'nama' => $data['nama'],
            'mode' => $data['mode'],
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampilkan_pembahasan' => $request->boolean('tampilkan_pembahasan'),
            'leaderboard_live' => $request->boolean('leaderboard_live'),
            'max_peserta' => $data['max_peserta'] ?? null,
            'jeda_detik' => $data['jeda_detik'],
        ]);

        return redirect()->route('admin.sessions.show', $session)->with('sukses', 'Pengaturan sesi diperbarui.');
    }

    public function destroy(QuizSession $session): RedirectResponse
    {
        $sessionCopy = clone $session;
        $session->delete();
        SesiStatusUpdated::safeDispatch($sessionCopy, ['action' => 'deleted']);

        return redirect()->route('admin.sessions.index')->with('sukses', 'Sesi dihapus.');
    }

    public function openLobby(QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        if ($session->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Lobby hanya bisa dibuka dari status draft.']);
        }

        $session->update([
            'status' => 'lobby',
            'dibuka_at' => now(),
            'display_token' => $session->display_token ?: Str::random(48),
        ]);

        $fresh = $session->fresh();
        try {
            $engine->siarkanDisplay($fresh);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast display error: ' . $e->getMessage());
        }
        SesiStatusUpdated::safeDispatch($fresh);

        return back()->with('sukses', 'Lobby dibuka. Mode dan kuis sekarang terkunci.');
    }

    public function start(Request $request, QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        if ($session->status !== 'lobby') {
            throw ValidationException::withMessages(['status' => 'Sesi hanya bisa dimulai dari lobby.']);
        }

        if ($request->has('peserta_ids')) {
            $validated = $request->validate([
                'peserta_ids' => ['required', 'array', 'min:1'],
                'peserta_ids.*' => ['integer', 'exists:users,id'],
            ], [
                'peserta_ids.required' => 'Pilih minimal satu peserta yang hadir.',
                'peserta_ids.min' => 'Pilih minimal satu peserta yang hadir.',
            ]);

            $selectedIds = collect($validated['peserta_ids'])->map(fn ($id) => (int) $id)->all();
            $session->allowedParticipants()->sync($selectedIds);

            // Hapus attempt peserta yang tidak terpilih (status menunggu)
            $session->attempts()
                ->where('status', 'menunggu')
                ->whereNotIn('user_id', $selectedIds)
                ->delete();

            // Buat attempt untuk peserta terpilih jika belum ada
            foreach ($selectedIds as $uid) {
                Attempt::firstOrCreate(
                    ['quiz_session_id' => $session->id, 'user_id' => $uid],
                    ['status' => 'menunggu']
                );
            }
        }

        $engine->mulaiSesi($session);

        return back()->with('sukses', $session->mode === 'serempak'
            ? 'Sesi Serempak dimulai. Soal pertama sudah dibuka.'
            : 'Sesi Acak dimulai. Peserta bisa menekan Mulai mengerjakan.');
    }

    public function updateParticipants(Request $request, QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        if (! in_array($session->status, ['draft', 'lobby'], true)) {
            throw ValidationException::withMessages(['status' => 'Daftar peserta hanya bisa diubah sebelum sesi berjalan.']);
        }

        $validated = $request->validate([
            'peserta_ids' => ['required', 'array', 'min:1'],
            'peserta_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'peserta_ids.required' => 'Pilih minimal satu peserta yang hadir.',
            'peserta_ids.min' => 'Pilih minimal satu peserta yang hadir.',
        ]);

        $selectedIds = collect($validated['peserta_ids'])->map(fn ($id) => (int) $id)->all();
        $session->allowedParticipants()->sync($selectedIds);

        // Hapus attempt peserta yang tidak terpilih (status menunggu)
        $session->attempts()
            ->where('status', 'menunggu')
            ->whereNotIn('user_id', $selectedIds)
            ->delete();

        // Buat attempt untuk peserta terpilih jika belum ada
        foreach ($selectedIds as $uid) {
            Attempt::firstOrCreate(
                ['quiz_session_id' => $session->id, 'user_id' => $uid],
                ['status' => 'menunggu']
            );
        }

        $fresh = $session->fresh();
        try {
            $engine->siarkanDisplay($fresh);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast display error: ' . $e->getMessage());
        }
        SesiStatusUpdated::safeDispatch($fresh, ['action' => 'peserta_updated']);

        return back()->with('sukses', 'Daftar peserta hadir berhasil disimpan (' . count($selectedIds) . ' peserta dipilih).');
    }

    public function close(QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        if (! in_array($session->status, ['lobby', 'berjalan'], true)) {
            throw ValidationException::withMessages(['status' => 'Sesi ini sudah ditutup.']);
        }

        $engine->tutupSesi($session);
        SesiStatusUpdated::safeDispatch($session->fresh());

        return back()->with('sukses', 'Sesi ditutup. Semua attempt dikunci dan nilai dihitung.');
    }

    public function skip(QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        if ($session->mode !== 'serempak' || $session->fase !== 'soal_terbuka' || $session->soal_aktif_urutan === null) {
            throw ValidationException::withMessages(['soal' => 'Tidak ada soal Serempak yang sedang terbuka.']);
        }

        $engine->tutupSoal($session, $session->soal_aktif_urutan);

        return back()->with('sukses', 'Soal ditutup lebih awal. Jeda dimulai.');
    }
}
