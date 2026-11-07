<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('quiz_id')
                ->nullable()
                ->after('id')
                ->constrained('quizzes')
                ->cascadeOnDelete();
        });

        // Backfill quiz_id from quiz_questions pivot if records exist
        if (Schema::hasTable('quiz_questions')) {
            $pivotRows = DB::table('quiz_questions')->get();
            foreach ($pivotRows as $row) {
                DB::table('questions')
                    ->where('id', $row->question_id)
                    ->whereNull('quiz_id')
                    ->update(['quiz_id' => $row->quiz_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quiz_id');
        });
    }
};
