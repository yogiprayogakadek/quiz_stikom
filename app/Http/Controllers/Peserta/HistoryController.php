<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\QuizSession;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $dasar = $this->dasarRiwayat($user);

        $jenis = $request->query('jenis');
        $jenis = in_array($jenis, ['simulasi', 'semua'], true) ? $jenis : 'real';

        $status = $request->query('status');
        $status = in_array($status, ['final', 'pending'], true) ? $status : 'semua';

        $kelasList = $this->kelasRiwayat($dasar);

        $kelasFilter = (int) $request->query('kelas', 0);
        $kelasFilter = $kelasList->contains(fn (Kelas $kelas) => $kelas->id === $kelasFilter)
            ? $kelasFilter
            : null;

        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $riwayat = (clone $dasar)
            ->with(['quizSession.quiz', 'quizSession.kelas'])
            ->withCount(['answers as essay_pending_count' => $this->penilaianTertunda()])
            ->when($jenis !== 'semua', fn (Builder $query) => $query->whereHas(
                'quizSession.quiz',
                fn (Builder $quiz) => $quiz->where('is_percobaan', $jenis === 'simulasi')
            ))
            ->when($status === 'pending', fn (Builder $query) => $query->whereHas('answers', $this->penilaianTertunda()))
            ->when($status === 'final', fn (Builder $query) => $query->whereDoesntHave('answers', $this->penilaianTertunda()))
            ->when($kelasFilter !== null, fn (Builder $query) => $query->whereHas(
                'quizSession',
                fn (Builder $session) => $session->where('kelas_id', $kelasFilter)
            ))
            ->when($cari !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($cari) {
                $query->whereHas('quizSession.quiz', fn (Builder $quiz) => $quiz->where('judul', 'like', '%'.$cari.'%'))
                    ->orWhereHas('quizSession', fn (Builder $session) => $session
                        ->where('nama', 'like', '%'.$cari.'%')
                        ->orWhereHas('kelas', fn (Builder $kelas) => $kelas->where('nama', 'like', '%'.$cari.'%')));
            }))
            ->orderByDesc('selesai_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('peserta.history', [
            'riwayat' => $riwayat,
            'kelasList' => $kelasList,
            'jenis' => $jenis,
            'status' => $status,
            'kelasFilter' => $kelasFilter,
            'cari' => $cari,
            'punyaRiwayat' => (clone $dasar)->exists(),
        ]);
    }

    private function dasarRiwayat(User $user): Builder
    {
        return Attempt::query()
            ->where('user_id', $user->id)
            ->mengikuti()
            ->where('status', 'selesai');
    }

    private function kelasRiwayat(Builder $dasar): Collection
    {
        return Kelas::query()
            ->whereIn('id', QuizSession::query()
                ->whereNotNull('kelas_id')
                ->distinct()
                ->select('kelas_id')
                ->whereIn('id', (clone $dasar)->select('quiz_session_id')))
            ->orderBy('nama')
            ->get();
    }

    private function penilaianTertunda(): Closure
    {
        return fn (Builder $query) => $query
            ->whereNotNull('jawaban_teks')
            ->where('jawaban_teks', '!=', '')
            ->whereNull('dinilai_at')
            ->whereHas('question', fn (Builder $question) => $question->where('tipe', 'essay'));
    }
}
