<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('kode_akses', 16)->nullable()->after('nama');
            $table->unsignedSmallInteger('batas_telat_menit')->nullable()->after('scheduled_end_at');
            $table->boolean('navigasi_sekuensial')->default(false)->after('acak_opsi');
            $table->boolean('timer_per_soal')->default(false)->after('navigasi_sekuensial');
            $table->boolean('pisah_konteks_soal')->default(false)->after('timer_per_soal');
            $table->boolean('watermark_layar')->default(false)->after('pisah_konteks_soal');
            $table->boolean('webcam_proctoring')->default(false)->after('clipboard_policy');
            $table->string('screen_proctoring', 24)->default('off')->after('webcam_proctoring');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedInteger('posisi_terkunci_urutan')->default(1)->after('status');
            $table->string('initial_ip', 45)->nullable()->after('peringatan_mulai_at');
            $table->unsignedSmallInteger('ip_change_count')->default(0)->after('initial_ip');
            $table->unsignedBigInteger('screen_peek_until')->nullable()->after('ip_change_count');
        });

        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('dibuka_at')->nullable()->after('highlight_opsi_json');
            $table->unsignedBigInteger('berakhir_at')->nullable()->after('dibuka_at');
            $table->unsignedBigInteger('terkunci_at')->nullable()->after('berakhir_at');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->json('typing_metrics_json')->nullable()->after('waktu_jawab_ms');
        });

        Schema::create('attempt_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('question_urutan')->nullable();
            $table->string('capture_source', 16);
            $table->string('trigger_type', 32);
            $table->string('path', 255);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedTinyInteger('luminance')->nullable();
            $table->timestamp('captured_at')->index();

            $table->index(['attempt_id', 'capture_source', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_snapshots');

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('typing_metrics_json');
        });

        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->dropColumn(['dibuka_at', 'berakhir_at', 'terkunci_at']);
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['posisi_terkunci_urutan', 'initial_ip', 'ip_change_count', 'screen_peek_until']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'kode_akses',
                'batas_telat_menit',
                'navigasi_sekuensial',
                'timer_per_soal',
                'pisah_konteks_soal',
                'watermark_layar',
                'webcam_proctoring',
                'screen_proctoring',
            ]);
        });
    }
};
