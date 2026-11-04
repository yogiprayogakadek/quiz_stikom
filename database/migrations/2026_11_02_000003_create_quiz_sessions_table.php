<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained();
            $table->string('nama');
            $table->enum('mode', ['acak', 'serempak']);
            $table->boolean('acak_opsi')->default(true);
            $table->boolean('tampilkan_pembahasan')->default(false);
            $table->boolean('leaderboard_live')->default(true);
            $table->unsignedInteger('max_peserta')->nullable();
            $table->unsignedSmallInteger('jeda_detik')->default(5);
            $table->enum('status', ['draft', 'lobby', 'berjalan', 'selesai'])->default('draft');
            $table->enum('fase', ['soal_terbuka', 'jeda'])->nullable();
            $table->unsignedInteger('soal_aktif_urutan')->nullable();
            $table->unsignedBigInteger('soal_mulai_at')->nullable();
            $table->unsignedBigInteger('soal_berakhir_at')->nullable();
            $table->string('display_token', 64)->unique();
            $table->timestamp('dibuka_at')->nullable();
            $table->timestamp('ditutup_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'mode']);
        });

        Schema::create('session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->unsignedInteger('urutan');
            $table->unique(['quiz_session_id', 'urutan']);
            $table->unique(['quiz_session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_questions');
        Schema::dropIfExists('quiz_sessions');
    }
};
