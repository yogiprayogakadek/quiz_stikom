<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('content_blocks_json')->nullable();
            $table->string('answer_format', 20)->default('rich_text');
            $table->json('answer_config_json')->nullable();
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->json('jawaban_data_json')->nullable();
        });

        Schema::create('question_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('original_name')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_media');

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('jawaban_data_json');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['content_blocks_json', 'answer_format', 'answer_config_json']);
        });
    }
};
