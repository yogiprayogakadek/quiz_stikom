<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Kelas;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(): View
    {
        $quizzes = Quiz::with('kelas')
            ->withCount(['questions', 'sessions'])
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('admin.quizzes.index', compact('quizzes'));
    }

    public function create(): View|RedirectResponse
    {
        $kelasList = Kelas::orderBy('nama')->get();

        if ($kelasList->isEmpty()) {
            return redirect()->route('admin.classes.index')
                ->withErrors(['kelas' => 'Buat kelas terlebih dahulu sebelum membuat kuis.']);
        }

        return view('admin.quizzes.create', compact('kelasList'));
    }

    public function store(StoreQuizRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quiz = DB::transaction(function () use ($data, $request) {
            return Quiz::create([
                'kelas_id' => $data['kelas_id'],
                'judul' => $data['judul'],
                'jumlah_soal' => $data['jumlah_soal'] ?? null,
                'acak_opsi' => $request->boolean('acak_opsi', true),
                'auto_grade_essay' => $request->boolean('auto_grade_essay'),
                'is_percobaan' => $request->boolean('is_percobaan'),
                'remidi_aktif' => $request->boolean('remidi_aktif'),
                'remidi_nilai_min' => $data['remidi_nilai_min'] ?? null,
                'created_by' => request()->user()->id,
            ]);
        });

        return redirect()->route('admin.quizzes.questions.index', $quiz)->with('sukses', 'Kuis dibuat. Silakan tambahkan soal untuk kuis ini.');
    }

    public function edit(Quiz $quiz): View
    {
        $kelasList = Kelas::orderBy('nama')->get();

        return view('admin.quizzes.edit', compact('quiz', 'kelasList'));
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz): RedirectResponse
    {
        if ($quiz->sessions()->whereIn('status', ['lobby', 'berjalan'])->exists()) {
            return redirect()->route('admin.quizzes.index')
                ->withErrors(['kuis' => 'Kuis ini dipakai sesi yang sedang berjalan, jadi pengaturannya tidak bisa diubah.']);
        }

        $data = $request->validated();

        $quiz->update([
            'kelas_id' => $data['kelas_id'],
            'judul' => $data['judul'],
            'jumlah_soal' => $data['jumlah_soal'] ?? null,
            'acak_opsi' => $request->boolean('acak_opsi'),
            'auto_grade_essay' => $request->boolean('auto_grade_essay'),
            'is_percobaan' => $request->boolean('is_percobaan'),
            'remidi_aktif' => $request->boolean('remidi_aktif'),
            'remidi_nilai_min' => $data['remidi_nilai_min'] ?? null,
        ]);

        return redirect()->route('admin.quizzes.index')->with('sukses', 'Kuis diperbarui.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        if ($quiz->sessions()->exists()) {
            return redirect()->route('admin.quizzes.index')
                ->withErrors(['kuis' => 'Kuis ini sudah dipakai sesi, jadi tidak bisa dihapus.']);
        }

        $quiz->delete();

        return redirect()->route('admin.quizzes.index')->with('sukses', 'Kuis dihapus.');
    }
}
