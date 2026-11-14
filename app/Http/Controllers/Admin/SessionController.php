<?php

namespace App\Http\Controllers\Admin;

use App\Events\PesertaDikeluarkan;
use App\Events\PesertaDipaksaKumpul;
use App\Events\PesertaWaktuDitambah;
use App\Events\SesiStatusUpdated;
use App\Events\SessionMonitorUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\Kelas;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $sessions = QuizSession::with('quiz', 'remidi')
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
        $kelas = Kelas::orderBy('nama')->get();

        return view('admin.sessions.create', compact('quizzes', 'kelas'));
    }

    public function store(StoreSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $pembahasanMode = $data['pembahasan_mode']
            ?? ($request->boolean('tampilkan_pembahasan') ? 'terbuka' : 'tersembunyi');

        $session = QuizSession::create([
            'quiz_id' => $data['quiz_id'],
            'kelas_id' => $data['kelas_id'] ?? null,
            'nama' => $data['nama'],
            'mode' => $data['mode'],
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampilkan_pembahasan' => $pembahasanMode === 'terbuka',
            'pembahasan_mode' => $pembahasanMode,
            'leaderboard_live' => $request->boolean('leaderboard_live'),
            'max_peserta' => $data['max_peserta'] ?? null,
            'max_pelanggaran' => $data['max_pelanggaran'] ?? null,
            'durasi_peringatan' => $data['durasi_peringatan'] ?? 30,
            'jeda_detik' => $data['jeda_detik'],
            'status' => 'draft',
            'display_token' => Str::random(48),
            'created_by' => $request->user()->id,
        ]);

        if ($session->kelas_id !== null) {
            $session->allowedParticipants()->sync($session->kelas->peserta()->pluck('users.id')->all());
        }

        return redirect()->route('admin.sessions.show', $session)->with('sukses', 'Sesi dibuat. Buka lobby saat peserta siap bergabung.');
    }

    public function show(QuizSession $session): View
    {
        $session->load('quiz', 'kelas', 'remidi');

        $data = $this->dataMonitor($session);

        $allParticipants = $session->kelas_id !== null
            ? $session->kelas->peserta()->orderBy('nama')->get()
            : User::where('role', 'peserta')->inCurrentOrganization()->orderBy('nama')->get();

        $allowedParticipantIds = $session->allowedParticipants()->pluck('users.id')->all();

        // Jika belum ada filter peserta khusus yang disimpan, tandai semua peserta terdaftar secara default
        if (empty($allowedParticipantIds)) {
            $allowedParticipantIds = $allParticipants->pluck('id')->all();
        }

        return view('admin.sessions.show', $data + compact(
            'session',
            'allParticipants',
            'allowedParticipantIds'
        ));
    }

    /**
     * Potongan halaman yang dipakai polling halaman admin supaya daftar
     * peserta dan statusnya ikut berubah saat peserta bergabung atau menjawab.
     */
    public function monitor(QuizSession $session): JsonResponse
    {
        $session->load('quiz');

        $data = $this->dataMonitor($session) + ['session' => $session];

        return response()->json([
            'status' => view('admin.sessions._status', $data)->render(),
            'pembahasan' => view('admin.sessions._pembahasan', $data)->render(),
            'peserta' => view('admin.sessions._peserta', $data)->render(),
            'proctoring' => view('admin.sessions._proctoring', $data)->render(),
            'proctoring_count' => $data['kejadianTerbaru']->count(),
            'online_count' => $data['onlineCount'],
        ]);
    }

    /**
     * @return array{attempts: Collection<int, Attempt>, soalAktif: mixed, sudahMenjawab: int, totalPeserta: int}
     */
    private function dataMonitor(QuizSession $session): array
    {
        $attempts = $session->attempts()
            ->with('user')
            ->orderByDesc('skor_total')
            ->orderBy('created_at')
            ->get();

        $soalAktif = $session->soalAktif();

        if ($soalAktif !== null) {
            $soalAktif->load('question');
        }

        $sudahMenjawab = $session->soal_aktif_urutan !== null && $soalAktif !== null
            ? Answer::query()
                ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
                ->where('question_id', $soalAktif->question_id)
                ->count()
            : 0;

        $kejadianTerbaru = AttemptEvent::query()
            ->whereHas('attempt', fn ($q) => $q->where('quiz_session_id', $session->id))
            ->with('attempt.user')
            ->latest('id')
            ->limit(25)
            ->get();

        return [
            'attempts' => $attempts,
            'soalAktif' => $soalAktif,
            'sudahMenjawab' => $sudahMenjawab,
            'totalPeserta' => $attempts->whereNotNull('bergabung_at')->count(),
            'pembahasanSelesai' => $attempts->whereNotNull('bergabung_at')->where('status', 'selesai')->count(),
            'onlineCount' => $attempts->filter(fn (Attempt $attempt) => $attempt->isOnline())->count(),
            'kejadianTerbaru' => $kejadianTerbaru,
        ];
    }

    public function edit(QuizSession $session): View
    {
        $quizzes = Quiz::withCount('questions')->orderBy('judul')->get();
        $kelas = Kelas::orderBy('nama')->get();

        return view('admin.sessions.edit', compact('session', 'quizzes', 'kelas'));
    }

    public function update(UpdateSessionRequest $request, QuizSession $session): RedirectResponse
    {
        $data = $request->validated();
        $kelasLama = $session->kelas_id;

        if ($session->status !== 'draft') {
            if ((int) $data['quiz_id'] !== $session->quiz_id
                || $data['mode'] !== $session->mode
                || (int) ($data['kelas_id'] ?? 0) !== (int) $kelasLama) {
                throw ValidationException::withMessages([
                    'mode' => 'Mode, kuis, dan kelas terkunci karena sesi sudah keluar dari status draft.',
                ]);
            }
        }

        $pembahasanMode = $data['pembahasan_mode']
            ?? ($request->boolean('tampilkan_pembahasan') ? 'terbuka' : 'tersembunyi');

        $session->update([
            'quiz_id' => $data['quiz_id'],
            'kelas_id' => $data['kelas_id'] ?? null,
            'nama' => $data['nama'],
            'mode' => $data['mode'],
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampilkan_pembahasan' => $pembahasanMode === 'terbuka',
            'pembahasan_mode' => $pembahasanMode,
            'leaderboard_live' => $request->boolean('leaderboard_live'),
            'max_peserta' => $data['max_peserta'] ?? null,
            'max_pelanggaran' => $data['max_pelanggaran'] ?? null,
            'durasi_peringatan' => $data['durasi_peringatan'] ?? 30,
            'jeda_detik' => $data['jeda_detik'],
        ]);

        if ((int) $session->kelas_id !== (int) $kelasLama) {
            $session->allowedParticipants()->sync(
                $session->kelas_id === null ? [] : $session->kelas->peserta()->pluck('users.id')->all()
            );
        }

        return redirect()->route('admin.sessions.show', $session)->with('sukses', 'Pengaturan sesi diperbarui.');
    }

    public function pembahasan(Request $request, QuizSession $session, QuizEngine $engine): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:tersembunyi,otomatis,terbuka'],
        ]);

        $session->update([
            'pembahasan_mode' => $data['mode'],
            'tampilkan_pembahasan' => $data['mode'] === 'terbuka',
        ]);

        $fresh = $session->fresh();
        $engine->siarkanDisplay($fresh);
        SesiStatusUpdated::safeDispatch($fresh, ['action' => 'pembahasan_updated']);

        $label = match ($data['mode']) {
            'terbuka' => 'Pembahasan dibuka sekarang.',
            'otomatis' => 'Pembahasan akan dibuka setelah semua peserta selesai.',
            default => 'Pembahasan disembunyikan.',
        };

        return back()->with('sukses', $label);
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
            Log::warning('Broadcast display error: '.$e->getMessage());
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
            Log::warning('Broadcast display error: '.$e->getMessage());
        }
        SesiStatusUpdated::safeDispatch($fresh, ['action' => 'peserta_updated']);

        return back()->with('sukses', 'Daftar peserta hadir berhasil disimpan ('.count($selectedIds).' peserta dipilih).');
    }

    /**
     * Kumpulkan pekerjaan seorang peserta saat sesi masih berjalan.
     */
    public function forceFinish(Request $request, QuizSession $session, Attempt $attempt, QuizEngine $engine): RedirectResponse
    {
        $this->pastikanAttemptMilikSesi($session, $attempt);

        if ($session->status !== 'berjalan' || $attempt->status !== 'berjalan') {
            throw ValidationException::withMessages([
                'peserta' => 'Hanya pekerjaan yang sedang berjalan yang bisa dikumpulkan.',
            ]);
        }

        $attempt->loadMissing('user');
        $nama = $attempt->user?->nama ?? 'Peserta';
        $username = $attempt->user?->username;

        $engine->kumpulkanPaksa($attempt);

        Log::notice('Admin mengumpulkan pekerjaan peserta secara paksa.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'attempt_id' => $attempt->id,
            'peserta' => $nama,
            'peserta_username' => $username,
            'quiz_session_id' => $session->id,
        ]);

        $fresh = $session->fresh();
        $engine->siarkanDisplay($fresh);
        SesiStatusUpdated::safeDispatch($fresh, ['action' => 'attempt_dikumpulkan']);
        SessionMonitorUpdated::safeDispatch($session->id, ['action' => 'force_finish']);
        PesertaDipaksaKumpul::safeDispatch($session->id, $attempt->user_id, $attempt->id);

        return back()->with('sukses', 'Pekerjaan '.$nama.' dikumpulkan. Nilai dihitung dari jawaban yang tersimpan.');
    }

    /**
     * Keluarkan peserta dari sesi: cabut izin ikut serta dan hapus attempt-nya.
     */
    public function kick(Request $request, QuizSession $session, Attempt $attempt, QuizEngine $engine): RedirectResponse
    {
        $this->pastikanAttemptMilikSesi($session, $attempt);

        if (! in_array($session->status, ['lobby', 'berjalan'], true)) {
            throw ValidationException::withMessages([
                'peserta' => 'Peserta hanya bisa dikeluarkan saat lobby atau sesi berjalan.',
            ]);
        }

        $attempt->loadMissing('user');
        $nama = $attempt->user?->nama ?? 'Peserta';
        $username = $attempt->user?->username;
        $userId = $attempt->user_id;

        DB::transaction(function () use ($session, $attempt, $userId) {
            // Sesi tanpa daftar peserta berarti terbuka untuk semua. Kunci daftarnya
            // dulu supaya peserta yang dikeluarkan tidak bisa langsung bergabung lagi.
            $diizinkan = $session->allowedParticipants()->pluck('users.id')->all();

            if ($diizinkan === []) {
                $diizinkan = User::where('role', 'peserta')->inCurrentOrganization()->pluck('id')->all();
            }

            $session->allowedParticipants()->sync(array_values(array_diff($diizinkan, [$userId])));
            $attempt->delete();
        });

        Log::notice('Admin mengeluarkan peserta dari sesi.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'peserta' => $nama,
            'peserta_username' => $username,
            'quiz_session_id' => $session->id,
        ]);

        $fresh = $session->fresh();
        $engine->siarkanDisplay($fresh);
        SesiStatusUpdated::safeDispatch($fresh, ['action' => 'peserta_dikeluarkan']);
        SessionMonitorUpdated::safeDispatch($session->id, ['action' => 'kick', 'user_id' => $userId]);
        PesertaDikeluarkan::safeDispatch($session->id, $userId);

        return back()->with('sukses', $nama.' dikeluarkan dari sesi ini dan tidak bisa bergabung lagi.');
    }

    /**
     * Tambah waktu ujian untuk peserta tertentu (mode acak).
     */
    public function addTime(Request $request, QuizSession $session, Attempt $attempt): RedirectResponse
    {
        $this->pastikanAttemptMilikSesi($session, $attempt);

        if ($session->mode !== 'acak' || $session->status !== 'berjalan' || $attempt->status !== 'berjalan') {
            throw ValidationException::withMessages([
                'peserta' => 'Penambahan waktu hanya bisa dilakukan saat sesi dan attempt sedang berjalan (mode acak).',
            ]);
        }

        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $this->tambahWaktu($session, [$attempt->id], (int) $data['minutes']);

        return back()->with('sukses', "Waktu ujian berhasil ditambah {$data['minutes']} menit.");
    }

    public function addTimeBulk(Request $request, QuizSession $session): RedirectResponse
    {
        if ($session->mode !== 'acak' || $session->status !== 'berjalan') {
            throw ValidationException::withMessages([
                'peserta' => 'Penambahan waktu hanya bisa dilakukan saat sesi Mode Acak sedang berjalan.',
            ]);
        }

        $data = $request->validate([
            'attempt_ids' => ['required', 'array', 'min:1'],
            'attempt_ids.*' => ['integer'],
            'minutes' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['attempt_ids'])));
        $jumlahBerjalan = $session->attempts()
            ->whereIn('id', $ids)
            ->where('status', 'berjalan')
            ->count();

        if ($jumlahBerjalan !== count($ids)) {
            throw ValidationException::withMessages([
                'attempt_ids' => 'Pilih peserta yang masih mengerjakan ujian di sesi ini.',
            ]);
        }

        $this->tambahWaktu($session, $ids, (int) $data['minutes']);

        return back()->with('sukses', "Waktu ujian {$jumlahBerjalan} peserta berhasil ditambah {$data['minutes']} menit.");
    }

    /**
     * @param  array<int, int>  $attemptIds
     */
    private function tambahWaktu(QuizSession $session, array $attemptIds, int $minutes): void
    {
        $nowMs = now()->getTimestampMs();

        $attempts = DB::transaction(function () use ($session, $attemptIds, $minutes, $nowMs) {
            $attempts = $session->attempts()
                ->whereIn('id', $attemptIds)
                ->where('status', 'berjalan')
                ->with('user')
                ->lockForUpdate()
                ->get();

            if ($attempts->count() !== count($attemptIds)) {
                throw ValidationException::withMessages([
                    'peserta' => 'Sebagian peserta sudah tidak mengerjakan ujian. Muat ulang lalu coba lagi.',
                ]);
            }

            foreach ($attempts as $attempt) {
                $basis = ($attempt->batas_akhir_at && $attempt->batas_akhir_at > $nowMs)
                    ? (int) $attempt->batas_akhir_at
                    : $nowMs;

                $attempt->batas_akhir_at = $basis + ($minutes * 60 * 1000);
                $attempt->save();
            }

            return $attempts;
        });

        foreach ($attempts as $attempt) {
            PesertaWaktuDitambah::safeDispatch(
                $session->id,
                $attempt->user_id,
                $attempt->id,
                (int) $attempt->batas_akhir_at,
                $minutes,
                $nowMs,
            );
        }

        SessionMonitorUpdated::safeDispatch($session->id, [
            'action' => 'add_time',
            'user_ids' => $attempts->pluck('user_id')->all(),
            'attempt_ids' => $attempts->pluck('id')->all(),
            'minutes' => $minutes,
        ]);
    }

    /**
     * Reset attempt peserta agar bisa masuk atau mengulang dari awal tanpa dikeluarkan dari sesi.
     */
    public function resetAttempt(Request $request, QuizSession $session, Attempt $attempt): RedirectResponse
    {
        $this->pastikanAttemptMilikSesi($session, $attempt);

        $attempt->loadMissing('user');
        $nama = $attempt->user?->nama ?? 'Peserta';
        $userId = $attempt->user_id;

        DB::transaction(function () use ($attempt) {
            $attempt->delete();
        });

        Log::notice('Admin mereset attempt peserta via ruang kendali.', [
            'admin_id' => $request->user()->id,
            'admin_username' => $request->user()->username,
            'peserta' => $nama,
            'user_id' => $userId,
            'quiz_session_id' => $session->id,
        ]);

        SessionMonitorUpdated::safeDispatch($session->id, [
            'action' => 'reset_attempt',
            'user_id' => $userId,
        ]);
        PesertaDikeluarkan::safeDispatch($session->id, $userId);

        return back()->with('sukses', "Attempt untuk {$nama} berhasil direset. Peserta dapat masuk kembali.");
    }

    private function pastikanAttemptMilikSesi(QuizSession $session, Attempt $attempt): void
    {
        abort_unless((int) $attempt->quiz_session_id === (int) $session->id, 404);
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
