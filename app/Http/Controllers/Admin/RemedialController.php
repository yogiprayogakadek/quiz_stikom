<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Quiz;
use App\Models\QuizGrade;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\NilaiService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RemedialController extends Controller
{
    public function create(QuizSession $session): View
    {
        $this->pastikanBolehRemidi($session);

        $session->load('quiz.kelas');

        $peserta = $session->attempts()
            ->bergabung()
            ->where('status', 'selesai')
            ->with('user')
            ->get()
            ->map(fn (Attempt $attempt) => [
                'user' => $attempt->user,
                'nilai' => app(NilaiService::class)->attempt($attempt),
            ])
            ->sortBy('user.nama')
            ->values();

        $kuisRemidi = Quiz::withCount('questions')
            ->whereKeyNot($session->quiz_id)
            ->where('is_percobaan', false)
            ->orderBy('judul')
            ->get();

        return view('admin.sessions.remidi', compact('session', 'peserta', 'kuisRemidi'));
    }

    public function store(Request $request, QuizSession $session): RedirectResponse
    {
        $this->pastikanBolehRemidi($session);

        $pesertaIds = $session->attempts()
            ->bergabung()
            ->where('status', 'selesai')
            ->pluck('user_id')
            ->all();

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:160'],
            'sumber_soal' => ['required', 'in:lama,baru'],
            'quiz_id' => ['nullable', 'required_if:sumber_soal,baru', 'integer',
                Rule::exists('quizzes', 'id')
                    ->where('organization_id', app(OrganizationContext::class)->id())
                    ->where('is_percobaan', false)],
            'peserta_ids' => ['required', 'array', 'min:1'],
            'peserta_ids.*' => ['integer', Rule::in($pesertaIds)],
        ], [
            'nama.required' => 'Nama sesi remidi wajib diisi.',
            'peserta_ids.required' => 'Pilih minimal satu peserta remidi.',
            'peserta_ids.*.in' => 'Peserta yang dipilih tidak mengikuti sesi awal ini.',
            'quiz_id.required_if' => 'Pilih kuis sumber soal baru.',
        ]);

        if ($data['sumber_soal'] === 'baru' && (int) ($data['quiz_id'] ?? 0) === (int) $session->quiz_id) {
            throw ValidationException::withMessages([
                'quiz_id' => 'Pilih kuis lain untuk soal remidi baru.',
            ]);
        }

        $quizId = $data['sumber_soal'] === 'lama' ? $session->quiz_id : (int) $data['quiz_id'];
        $quiz = Quiz::withCount('questions')->findOrFail($quizId);

        $remidi = QuizSession::create([
            'quiz_id' => $quizId,
            'kelas_id' => $session->kelas_id,
            'nama' => $data['nama'],
            'mode' => 'acak',
            'jenis' => 'remidi',
            'remidi_dari_session_id' => $session->id,
            'remidi_soal' => $data['sumber_soal'],
            'acak_opsi' => $session->acak_opsi,
            'tampilkan_pembahasan' => false,
            'pembahasan_mode' => 'tersembunyi',
            'leaderboard_live' => true,
            'max_peserta' => null,
            'jeda_detik' => 5,
            'status' => 'draft',
            'display_token' => Str::random(48),
            'created_by' => $request->user()->id,
        ]);

        $remidi->allowedParticipants()->sync($data['peserta_ids']);

        foreach ($data['peserta_ids'] as $userId) {
            Attempt::firstOrCreate(
                ['quiz_session_id' => $remidi->id, 'user_id' => $userId],
                ['status' => 'menunggu'],
            );
        }

        AuditLog::record('remedial_session_created', 'quiz_session', $remidi->id, [
            'sesi_awal_id' => $session->id,
            'sumber_soal' => $data['sumber_soal'],
            'quiz_id' => $quizId,
            'peserta' => count($data['peserta_ids']),
        ], $session->organization_id);

        $pesan = 'Sesi remidi dibuat. Buka lobby saat peserta siap.';

        if ($quiz->questions_count === 0) {
            $pesan .= ' Kuis remidi belum punya soal, unggah soal dulu.';
        }

        return redirect()->route('admin.sessions.show', $remidi)->with('sukses', $pesan);
    }

    public function buatKuis(Request $request, QuizSession $session): RedirectResponse
    {
        $this->pastikanBolehRemidi($session);

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
        ]);

        $quiz = Quiz::create([
            'kelas_id' => $session->quiz->kelas_id,
            'judul' => $data['judul'],
            'jumlah_soal' => null,
            'acak_opsi' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.quizzes.questions.import', $quiz)
            ->with('sukses', 'Kuis soal remidi dibuat. Unggah soal baru, lalu kembali ke wizard remidi.');
    }

    public function laporan(QuizSession $session, NilaiService $nilai): View
    {
        abort_unless($session->jenis === 'remidi', 404);

        $data = $this->barisLaporan($session, $nilai);

        return view('admin.reports.remidi', $data);
    }

    public function terapkan(Request $request, QuizSession $session, NilaiService $nilai): RedirectResponse
    {
        abort_unless($session->jenis === 'remidi', 404);

        $sesiAwal = $session->remidiDari;
        abort_if($sesiAwal === null, 404);

        $data = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $pesertaIds = $data['user_ids'] ?? $session->attempts()
            ->where('status', 'selesai')
            ->pluck('user_id')
            ->all();

        $attemptAwal = $sesiAwal->attempts()->whereIn('user_id', $pesertaIds)->get()->keyBy('user_id');
        $attemptRemidi = $session->attempts()->whereIn('user_id', $pesertaIds)->get()->keyBy('user_id');

        $diterapkan = 0;
        $dilewati = 0;

        DB::transaction(function () use ($pesertaIds, $attemptAwal, $attemptRemidi, $sesiAwal, $session, $nilai, $request, &$diterapkan, &$dilewati) {
            foreach ($pesertaIds as $userId) {
                $awal = $attemptAwal->get($userId);
                $remidi = $attemptRemidi->get($userId);

                if ($awal?->status !== 'selesai' || $remidi?->status !== 'selesai') {
                    $dilewati++;

                    continue;
                }

                $nilaiAwal = $nilai->attempt($awal);
                $nilaiRemidi = $nilai->attempt($remidi);

                $grade = QuizGrade::updateOrCreate(
                    ['sesi_awal_id' => $sesiAwal->id, 'user_id' => $userId],
                    [
                        'organization_id' => $sesiAwal->organization_id,
                        'sesi_remidi_id' => $session->id,
                        'nilai_awal' => $nilaiAwal,
                        'nilai_remidi' => $nilaiRemidi,
                        'nilai_akhir' => max($nilaiAwal, $nilaiRemidi),
                        'sumber' => $nilaiRemidi > $nilaiAwal ? 'remidi' : 'awal',
                        'ditetapkan_oleh' => $request->user()->id,
                        'ditetapkan_at' => now(),
                    ],
                );

                AuditLog::record('remedial_best_score_applied', 'quiz_grade', $grade->id, [
                    'sesi_awal_id' => $sesiAwal->id,
                    'sesi_remidi_id' => $session->id,
                    'user_id' => $userId,
                    'nilai_awal' => $nilaiAwal,
                    'nilai_remidi' => $nilaiRemidi,
                    'nilai_akhir' => $grade->nilai_akhir,
                ], $sesiAwal->organization_id);

                $diterapkan++;
            }
        });

        return back()->with('sukses', "Nilai terbaik diterapkan untuk {$diterapkan} peserta. {$dilewati} peserta dilewati karena belum menyelesaikan remidi.");
    }

    public function export(QuizSession $session, NilaiService $nilai): StreamedResponse
    {
        abort_unless($session->jenis === 'remidi', 404);

        $data = $this->barisLaporan($session, $nilai);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['nama', 'username', 'nilai_awal', 'nilai_remidi', 'selisih', 'nilai_akhir', 'sumber', 'diterapkan']);

            foreach ($data['baris'] as $row) {
                fputcsv($out, [
                    $row['user']->nama,
                    $row['user']->username,
                    $row['nilai_awal'] ?? '',
                    $row['nilai_remidi'] ?? '',
                    $row['selisih'] ?? '',
                    $row['nilai_akhir'] ?? '',
                    $row['sumber'],
                    $row['diterapkan'] ? 'ya' : 'tidak',
                ]);
            }

            fclose($out);
        }, 'remidi-'.$session->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function barisLaporan(QuizSession $session, NilaiService $nilai): array
    {
        $session->load(['quiz', 'remidiDari.quiz']);
        $sesiAwal = $session->remidiDari;
        abort_if($sesiAwal === null, 404);

        $pesertaIds = $session->allowedParticipants()->pluck('users.id');

        if ($pesertaIds->isEmpty()) {
            $pesertaIds = $session->attempts()->pluck('user_id');
        }

        $users = User::withTrashed()->whereIn('id', $pesertaIds)->orderBy('nama')->get();
        $attemptAwal = $sesiAwal->attempts()->whereIn('user_id', $pesertaIds)->get()->keyBy('user_id');
        $attemptRemidi = $session->attempts()->whereIn('user_id', $pesertaIds)->get()->keyBy('user_id');
        $grades = QuizGrade::where('sesi_awal_id', $sesiAwal->id)->whereIn('user_id', $pesertaIds)->get()->keyBy('user_id');
        $kkm = $sesiAwal->quiz->remidi_nilai_min;

        $baris = $users->map(function (User $user) use ($attemptAwal, $attemptRemidi, $grades, $nilai, $kkm) {
            $awalAttempt = $attemptAwal->get($user->id);
            $remidiAttempt = $attemptRemidi->get($user->id);
            $nilaiAwal = $awalAttempt?->status === 'selesai' ? $nilai->attempt($awalAttempt) : null;
            $nilaiRemidi = $remidiAttempt?->status === 'selesai' ? $nilai->attempt($remidiAttempt) : null;
            $grade = $grades->get($user->id);
            $terbaik = $nilaiAwal !== null && $nilaiRemidi !== null
                ? max($nilaiAwal, $nilaiRemidi)
                : ($nilaiAwal ?? $nilaiRemidi);

            return [
                'user' => $user,
                'nilai_awal' => $nilaiAwal,
                'nilai_remidi' => $nilaiRemidi,
                'selisih' => $nilaiAwal !== null && $nilaiRemidi !== null ? $nilaiRemidi - $nilaiAwal : null,
                'nilai_akhir' => $grade?->nilai_akhir ?? $terbaik,
                'sumber' => $grade?->sumber ?? ($nilaiRemidi !== null && $nilaiAwal !== null && $nilaiRemidi > $nilaiAwal ? 'remidi' : 'awal'),
                'diterapkan' => $grade !== null,
                'eligible' => $nilaiAwal !== null && ($kkm === null || $nilaiAwal < $kkm),
            ];
        })->values();

        $ringkasan = [
            'total' => $baris->count(),
            'sudah_remidi' => $baris->whereNotNull('nilai_remidi')->count(),
            'rata_awal' => round($baris->whereNotNull('nilai_awal')->avg('nilai_awal') ?? 0, 1),
            'rata_remidi' => round($baris->whereNotNull('nilai_remidi')->avg('nilai_remidi') ?? 0, 1),
            'naik' => $baris->where('selisih', '>', 0)->count(),
            'turun' => $baris->where('selisih', '<', 0)->count(),
            'tetap' => $baris->where('selisih', 0)->count(),
            'diterapkan' => $baris->where('diterapkan', true)->count(),
            'kkm' => $kkm,
        ];

        $peringkat = $baris
            ->whereNotNull('nilai_akhir')
            ->sortByDesc('nilai_akhir')
            ->values()
            ->take(10);

        return compact('session', 'sesiAwal', 'baris', 'ringkasan', 'peringkat');
    }

    private function pastikanBolehRemidi(QuizSession $session): void
    {
        $session->loadMissing('quiz');

        abort_unless($session->jenis === 'utama', 404);
        abort_unless($session->status === 'selesai', 404);
        abort_unless($session->quiz?->remidi_aktif, 404);
        abort_unless(! $session->quiz?->is_percobaan, 404);
    }
}
