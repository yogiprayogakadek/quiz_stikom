<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['menunggu', 'berjalan', 'selesai'])->default('menunggu');
            $table->unsignedBigInteger('mulai_at')->nullable();
            $table->unsignedBigInteger('batas_akhir_at')->nullable();
            $table->unsignedBigInteger('selesai_at')->nullable();
            $table->unsignedInteger('jumlah_benar')->default(0);
            $table->unsignedInteger('skor_total')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'quiz_session_id']);
            $table->index(['quiz_session_id', 'status']);
        });

        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->unsignedInteger('urutan');
            $table->json('urutan_opsi_json');
            $table->unique(['attempt_id', 'question_id']);
            $table->unique(['attempt_id', 'urutan']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->foreignId('option_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('waktu_jawab_ms')->nullable();
            $table->unsignedInteger('skor')->default(0);
            $table->timestamp('answered_at');
            $table->unique(['attempt_id', 'question_id']);
            $table->index(['question_id']);
        });

        Schema::create('attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->string('tipe', 40);
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['attempt_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_events');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('attempt_questions');
        Schema::dropIfExists('attempts');
    }
};
