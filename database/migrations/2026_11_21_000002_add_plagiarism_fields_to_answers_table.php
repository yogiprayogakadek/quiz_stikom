<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedTinyInteger('similarity_score')->nullable()->after('catatan_pengajar');
            $table->json('similarity_data')->nullable()->after('similarity_score');
            $table->timestamp('similarity_checked_at')->nullable()->after('similarity_data');
            $table->unsignedTinyInteger('ai_generated_score')->nullable()->after('similarity_checked_at');
            $table->text('ai_generated_reason')->nullable()->after('ai_generated_score');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_generated_reason');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn([
                'similarity_score',
                'similarity_data',
                'similarity_checked_at',
                'ai_generated_score',
                'ai_generated_reason',
                'ai_generated_at',
            ]);
        });
    }
};
