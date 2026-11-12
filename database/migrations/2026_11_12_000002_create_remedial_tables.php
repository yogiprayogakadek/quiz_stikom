<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('remidi_aktif')->default(false)->after('is_percobaan');
            $table->unsignedTinyInteger('remidi_nilai_min')->nullable()->after('remidi_aktif');
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('jenis', 20)->default('utama')->after('mode');
            $table->foreignId('remidi_dari_session_id')->nullable()->after('jenis')
                ->constrained('quiz_sessions')->nullOnDelete();
            $table->string('remidi_soal', 20)->nullable()->after('remidi_dari_session_id');
            $table->index(['quiz_id', 'jenis']);
        });

        Schema::create('quiz_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('sesi_awal_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignId('sesi_remidi_id')->nullable()->constrained('quiz_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_awal');
            $table->unsignedTinyInteger('nilai_remidi')->nullable();
            $table->unsignedTinyInteger('nilai_akhir');
            $table->string('sumber', 20)->default('awal');
            $table->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditetapkan_at')->nullable();
            $table->timestamps();

            $table->unique(['sesi_awal_id', 'user_id']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_grades');

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropIndex(['quiz_id', 'jenis']);
            $table->dropConstrainedForeignId('remidi_dari_session_id');
            $table->dropColumn(['jenis', 'remidi_soal']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['remidi_aktif', 'remidi_nilai_min']);
        });
    }
};
