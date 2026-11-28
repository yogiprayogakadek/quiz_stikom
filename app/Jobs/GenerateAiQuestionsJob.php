<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Services\AiQuestionService;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAiQuestionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $generationId) {}

    public function handle(OrganizationContext $context): void
    {
        $generation = AiGeneration::withoutGlobalScopes()->find($this->generationId);

        if ($generation === null || $generation->status !== 'pending') {
            return;
        }

        $context->force($generation->organization);

        try {
            $generation->update(['status' => 'running']);

            $params = $generation->params;
            $jumlahPg = (int) ($params['jumlah_pg'] ?? 0);
            $jumlahEssay = (int) ($params['jumlah_essay'] ?? 0);
            $ukuranBatch = 5;
            $reservedBatches = max(1, (int) ceil($jumlahPg / $ukuranBatch) + (int) ceil($jumlahEssay / $ukuranBatch));
            $generation->update(['total' => $reservedBatches]);

            $ai = app(AiQuestionService::class);
            $questions = [];
            $completedBatches = 0;

            try {
                for ($sisa = $jumlahPg; $sisa > 0; $sisa -= $ukuranBatch) {
                    $questions = array_merge($questions, $ai->generateQuestions(array_merge($params, [
                        'jumlah_pg' => min($ukuranBatch, $sisa),
                        'jumlah_essay' => 0,
                    ])));
                    $completedBatches++;
                    $generation->increment('done');
                }

                for ($sisa = $jumlahEssay; $sisa > 0; $sisa -= $ukuranBatch) {
                    $questions = array_merge($questions, $ai->generateQuestions(array_merge($params, [
                        'jumlah_pg' => 0,
                        'jumlah_essay' => min($ukuranBatch, $sisa),
                    ])));
                    $completedBatches++;
                    $generation->increment('done');
                }
            } catch (Throwable $e) {
                $unspent = max(0, $reservedBatches - $completedBatches);
                if ($generation->organization_id !== null && $unspent > 0) {
                    app(OrganizationUsage::class)->refundAi((int) $generation->organization_id, 'questions', $unspent);
                }

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
        } finally {
            $context->force(null);
        }
    }
}
