<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
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
        $quizzes = Quiz::withCount(['questions', 'sessions'])
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('admin.quizzes.index', compact('quizzes'));
    }

    public function create(): View
    {
        $questions = Question::with('options')->orderBy('teks')->get();

        return view('admin.quizzes.create', compact('questions'));
    }

    public function store(StoreQuizRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $quiz = Quiz::create([
                'judul' => $data['judul'],
                'jumlah_soal' => $data['jumlah_soal'] ?? null,
                'created_by' => request()->user()->id,
            ]);

            $quiz->questions()->sync($data['question_ids']);
        });

        return redirect()->route('admin.quizzes.index')->with('sukses', 'Kuis dibuat.');
    }

    public function edit(Quiz $quiz): View
    {
        $quiz->load('questions');
        $questions = Question::with('options')->orderBy('teks')->get();

        return view('admin.quizzes.edit', compact('quiz', 'questions'));
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz): RedirectResponse
    {
        if ($quiz->sessions()->whereIn('status', ['lobby', 'berjalan'])->exists()) {
            return redirect()->route('admin.quizzes.index')
                ->withErrors(['kuis' => 'Kuis ini dipakai sesi yang sedang berjalan, jadi soal dan pengaturannya tidak bisa diubah.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($quiz, $data) {
            $quiz->update([
                'judul' => $data['judul'],
                'jumlah_soal' => $data['jumlah_soal'] ?? null,
            ]);

            $quiz->questions()->sync($data['question_ids']);
        });

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
