<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizCloneService
{
    public function __construct(
        private OrganizationUsage $usage,
        private QuestionCloneService $questionCloneService,
    ) {}

    public function duplicate(Quiz $source, User $actor): Quiz
    {
        $source->load([
            'questions' => fn ($query) => $query->orderBy('id')->with([
                'options' => fn ($opt) => $opt->orderBy('id'),
                'media',
            ]),
        ]);

        $totalMediaBytes = (int) $source->questions->flatMap->media->sum('size_bytes');
        if (
            $source->organization_id !== null
            && $totalMediaBytes > 0
            && ! $this->usage->canStore((int) $source->organization_id, $totalMediaBytes)
        ) {
            throw ValidationException::withMessages([
                'kuis' => 'Kuota penyimpanan workspace tidak cukup untuk menggandakan gambar pada kuis ini.',
            ]);
        }

        return DB::transaction(function () use ($source, $actor) {
            $judulBaru = Str::limit($source->judul.' (Salinan)', 255, '');

            $attributes = [
                'source_quiz_id' => $source->id,
                'kelas_id' => $source->kelas_id,
                'organization_id' => $source->organization_id,
                'judul' => $judulBaru,
                'jumlah_soal' => $source->jumlah_soal,
                'acak_opsi' => $source->acak_opsi,
                'auto_grade_essay' => $source->auto_grade_essay,
                'is_percobaan' => $source->is_percobaan,
                'exam_tools_json' => $source->exam_tools_json,
                'remidi_nilai_min' => $source->remidi_nilai_min,
                'created_by' => $actor->id,
            ];

            foreach (['jumlah_soal_pg', 'jumlah_soal_essay', 'acak_soal', 'kelompokkan_tipe_soal'] as $extraCol) {
                if (array_key_exists($extraCol, $source->getAttributes())) {
                    $attributes[$extraCol] = $source->{$extraCol};
                }
            }

            $clone = Quiz::create($attributes);

            $this->questionCloneService->copyToQuiz($source->questions, $clone, 'kuis', false);

            AuditLog::record('quiz_duplicated', 'quiz', $clone->id, [
                'source_quiz_id' => $source->id,
                'title' => $clone->judul,
                'questions_count' => $source->questions->count(),
            ], $source->organization_id);

            return $clone;
        });
    }
}
