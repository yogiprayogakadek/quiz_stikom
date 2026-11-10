<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedInteger('ai_suggested_score')->nullable()->after('skor');
            $table->unsignedTinyInteger('ai_confidence')->nullable()->after('ai_suggested_score');
            $table->text('ai_feedback')->nullable()->after('ai_confidence');
            $table->string('ai_model')->nullable()->after('ai_feedback');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn([
                'ai_suggested_score',
                'ai_confidence',
                'ai_feedback',
                'ai_model',
                'ai_reviewed_at',
            ]);
        });
    }
};
