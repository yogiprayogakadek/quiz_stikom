<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('pelanggaran_action', 20)->default('submit')->after('durasi_peringatan');
            $table->unsignedInteger('pelanggaran_penalty_seconds')->nullable()->after('pelanggaran_action');
            $table->string('timing_mode', 20)->default('duration')->after('durasi_total_detik');
            $table->timestamp('scheduled_start_at')->nullable()->after('timing_mode');
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedInteger('retry_duration_detik')->nullable()->after('batas_akhir_at');
            $table->unsignedInteger('total_pengurangan_waktu_detik')->default(0)->after('jumlah_pelanggaran');
            $table->string('last_pelanggaran_action', 20)->nullable()->after('total_pengurangan_waktu_detik');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn([
                'retry_duration_detik',
                'total_pengurangan_waktu_detik',
                'last_pelanggaran_action',
            ]);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'pelanggaran_action',
                'pelanggaran_penalty_seconds',
                'timing_mode',
                'scheduled_start_at',
                'scheduled_end_at',
            ]);
        });
    }
};
