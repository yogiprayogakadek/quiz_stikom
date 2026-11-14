<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_pelanggaran')->nullable();
            $table->unsignedSmallInteger('durasi_peringatan')->default(30);
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_pelanggaran')->default(0);
            $table->unsignedBigInteger('peringatan_mulai_at')->nullable();
        });

        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->boolean('ragu_ragu')->default(false);
            $table->boolean('highlight_soal')->default(false);
            $table->json('highlight_opsi_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->dropColumn(['ragu_ragu', 'highlight_soal', 'highlight_opsi_json']);
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['jumlah_pelanggaran', 'peringatan_mulai_at']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn(['max_pelanggaran', 'durasi_peringatan']);
        });
    }
};
