<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuizCloneService
{
    public function __construct(
        private OrganizationUsage $usage,
        private QuestionMediaService $mediaService,
    ) {}

    public function duplicate(Quiz $source, User $actor): Quiz
    {
        $source->load(['questions' => fn ($query) => $query->orderBy('id')->with(['options' => fn ($opt) => $opt->orderBy('id'), 'media'])]);

        $totalMediaBytes = (int) $source->questions->flatMap->media->sum('size_bytes');
        if ($source->organization_id !== null && $totalMediaBytes > 0 && ! $this->usage->canStore((int) $source->organization_id, $totalMediaBytes)) {
            throw ValidationException::withMessages([
                'kuis' => 'Kuota penyimpanan workspace tidak cukup untuk menggandakan gambar pada kuis ini.',
            ]);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($source, $actor, &$storedPaths) {
                $judulBaru = Str::limit($source->judul.' (Salinan)', 255, '');

                $clone = Quiz::create([
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
                ]);

                foreach ($source->questions as $question) {
                    $copiedQuestion = Question::create([
                        'quiz_id' => $clone->id,
                        'organization_id' => $source->organization_id,
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
                    ]);

                    $contentBlocks = $question->content_blocks_json ?? [];
                    if ($contentBlocks !== []) {
                        $contentBlocks = $this->mediaService->cloneBlocks($question, $copiedQuestion, $contentBlocks, $storedPaths);
                    }

                    $subSoal = collect($question->sub_soal_json ?? [])->map(function (array $part) use ($question, $copiedQuestion, &$storedPaths) {
                        if (! empty($part['content_blocks']) && is_array($part['content_blocks'])) {
                            $part['content_blocks'] = $this->mediaService->cloneBlocks(
                                $question,
                                $copiedQuestion,
                                $part['content_blocks'],
                                $storedPaths,
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
                }

                AuditLog::record('quiz_duplicated', 'quiz', $clone->id, [
                    'source_quiz_id' => $source->id,
                    'title' => $clone->judul,
                    'questions_count' => $source->questions->count(),
                ], $source->organization_id);

                return $clone;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                $this->mediaService->cleanupPaths($storedPaths);
            }

            throw $exception;
        }
    }
}
