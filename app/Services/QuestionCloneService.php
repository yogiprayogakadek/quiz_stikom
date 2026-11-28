<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuestionCloneService
{
    public function __construct(
        private OrganizationUsage $usage,
        private QuestionMediaService $mediaService,
    ) {}

    /**
     * @param  iterable<int, Question>  $questions
     * @return Collection<int, Question>
     */
    public function copyToQuiz(
        iterable $questions,
        Quiz $targetQuiz,
        string $quotaErrorKey = 'questions',
        bool $recordAudit = true,
    ): Collection {
        $collection = $questions instanceof EloquentCollection
            ? $questions
            : new EloquentCollection(collect($questions)->all());

        $filtered = $collection
            ->filter(fn (Question $q) => (int) $q->organization_id === (int) $targetQuiz->organization_id)
            ->values();

        if ($filtered->isEmpty()) {
            return collect();
        }

        $filtered->loadMissing([
            'options' => fn ($query) => $query->orderBy('id'),
            'media',
        ]);

        $totalMediaBytes = (int) $filtered->flatMap->media->sum('size_bytes');
        if (
            $targetQuiz->organization_id !== null
            && $totalMediaBytes > 0
            && ! $this->usage->canStore((int) $targetQuiz->organization_id, $totalMediaBytes)
        ) {
            throw ValidationException::withMessages([
                $quotaErrorKey => 'Kuota penyimpanan workspace tidak cukup untuk menyalin gambar pada soal yang dipilih.',
            ]);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($filtered, $targetQuiz, $quotaErrorKey, $recordAudit, &$storedPaths) {
                $copied = collect();

                foreach ($filtered as $question) {
                    $attributes = [
                        'quiz_id' => $targetQuiz->id,
                        'organization_id' => $targetQuiz->organization_id,
                        'teks' => $question->teks,
                        'tipe' => $question->tipe,
                        'durasi_detik' => $question->durasi_detik,
                        'bobot' => $question->bobot,
                        'pembahasan' => $question->pembahasan,
                        'kunci_essay' => $question->kunci_essay,
                        'rubrik_essay' => $question->rubrik_essay,
                        'petunjuk_json' => $question->petunjuk_json,
                        'answer_format' => $question->answer_format,
                        'answer_config_json' => $question->answer_config_json,
                    ];

                    if (array_key_exists('urutan_tetap', $question->getAttributes())) {
                        $attributes['urutan_tetap'] = $question->urutan_tetap;
                    }

                    $copiedQuestion = Question::create($attributes);

                    $contentBlocks = $question->content_blocks_json ?? [];
                    if ($contentBlocks !== []) {
                        $contentBlocks = $this->mediaService->cloneBlocks(
                            $question,
                            $copiedQuestion,
                            $contentBlocks,
                            $storedPaths,
                            $quotaErrorKey,
                        );
                    }

                    $subSoal = collect($question->sub_soal_json ?? [])->map(function (array $part) use ($question, $copiedQuestion, $quotaErrorKey, &$storedPaths) {
                        if (! empty($part['content_blocks']) && is_array($part['content_blocks'])) {
                            $part['content_blocks'] = $this->mediaService->cloneBlocks(
                                $question,
                                $copiedQuestion,
                                $part['content_blocks'],
                                $storedPaths,
                                $quotaErrorKey,
                            );
                        }

                        return $part;
                    })->all();

                    if ($contentBlocks !== [] || $subSoal !== []) {
                        $copiedQuestion->update([
                            'content_blocks_json' => $contentBlocks !== [] ? $contentBlocks : null,
                            'sub_soal_json' => $subSoal !== [] ? $subSoal : null,
                        ]);
                    }

                    foreach ($question->options as $option) {
                        $copiedQuestion->options()->create([
                            'teks' => $option->teks,
                            'is_correct' => $option->is_correct,
                        ]);
                    }

                    $copied->push($copiedQuestion);
                }

                if ($recordAudit && $copied->isNotEmpty()) {
                    AuditLog::record('questions_copied_from_bank', 'quiz', $targetQuiz->id, [
                        'target_quiz_title' => $targetQuiz->judul,
                        'copied_count' => $copied->count(),
                        'source_question_ids' => $filtered->pluck('id')->all(),
                    ], $targetQuiz->organization_id);
                }

                return $copied;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                $this->mediaService->cleanupPaths($storedPaths);
            }

            throw $exception;
        }
    }
}
