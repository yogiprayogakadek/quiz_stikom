<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('requests')->default(1);
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        DB::table('ai_generations')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($generations) {
                $now = now();
                DB::table('ai_usage_events')->insert($generations->map(fn ($generation) => [
                    'organization_id' => $generation->organization_id,
                    'kind' => 'questions',
                    'requests' => max(1, (int) $generation->total),
                    'created_at' => $generation->created_at ?? $now,
                    'updated_at' => $generation->updated_at ?? $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
