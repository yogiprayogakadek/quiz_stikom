<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Services\AiQuestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAiQuestionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $generationId) {}

    public function handle(AiQuestionService $ai): void
    {
        $generation = AiGeneration::find($this->generationId);

        if ($generation === null || $generation->status !== 'pending') {
            return;
        }

        $generation->update(['status' => 'running']);

        $params = $generation->params;
        $jumlahPg = (int) ($params['jumlah_pg'] ?? 0);
        $jumlahEssay = (int) ($params['jumlah_essay'] ?? 0);
        $ukuranBatch = 5;
        $totalBatch = (int) ceil($jumlahPg / $ukuranBatch) + (int) ceil($jumlahEssay / $ukuranBatch);
        $generation->update(['total' => max(1, $totalBatch)]);

        $questions = [];

        try {
            for ($sisa = $jumlahPg; $sisa > 0; $sisa -= $ukuranBatch) {
                $questions = array_merge($questions, $ai->generateQuestions(array_merge($params, [
                    'jumlah_pg' => min($ukuranBatch, $sisa),
                    'jumlah_essay' => 0,
                ])));
                $generation->increment('done');
            }

            for ($sisa = $jumlahEssay; $sisa > 0; $sisa -= $ukuranBatch) {
                $questions = array_merge($questions, $ai->generateQuestions(array_merge($params, [
                    'jumlah_pg' => 0,
                    'jumlah_essay' => min($ukuranBatch, $sisa),
                ])));
                $generation->increment('done');
            }
        } catch (Throwable $e) {
            $generation->update([
                'status' => 'gagal',
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Tiap batch punya bobot sendiri; samakan sekali lagi dengan target nilai kuis.
        $weights = AiQuestionService::calculateWeights(
            (int) ($params['nilai_maksimal'] ?? 100),
            $jumlahPg,
            $jumlahEssay,
            (int) ($params['porsi_pg'] ?? 60),
        );

        $indexPg = 0;
        $indexEssay = 0;

        foreach ($questions as &$question) {
            if (($question['tipe'] ?? '') === 'essay') {
                $question['bobot'] = $weights['essay'][$indexEssay] ?? max(1, (int) ($question['bobot'] ?? 1));
                $indexEssay++;
            } else {
                $question['bobot'] = $weights['pg'][$indexPg] ?? max(1, (int) ($question['bobot'] ?? 1));
                $indexPg++;
            }
        }
        unset($question);

        $generation->update([
            'status' => 'selesai',
            'questions' => $questions,
            'done' => $generation->total,
            'error' => null,
        ]);
    }
}
