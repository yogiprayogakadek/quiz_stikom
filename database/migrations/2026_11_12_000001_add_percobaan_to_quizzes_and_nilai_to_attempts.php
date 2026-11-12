<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('is_percobaan')->default(false)->after('auto_grade_essay');
        });

        DB::table('quizzes')
            ->where('judul', 'Kuis Uji Coba')
            ->update(['is_percobaan' => true]);

        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('nilai')->nullable()->after('skor_total');
            $table->index(['user_id', 'nilai']);
        });

        $rows = DB::table('attempts')
            ->join('quiz_sessions', 'quiz_sessions.id', '=', 'attempts.quiz_session_id')
            ->select('attempts.id', 'attempts.skor_total', 'attempts.quiz_session_id', 'quiz_sessions.mode')
            ->orderBy('attempts.id')
            ->get();

        $totalSoal = [];

        foreach ($rows as $row) {
            $skor = (int) $row->skor_total;

            if ($row->mode === 'serempak') {
                $total = $totalSoal[$row->quiz_session_id]
                    ??= DB::table('session_questions')->where('quiz_session_id', $row->quiz_session_id)->count();
                $nilai = $total > 0 ? (int) round($skor / ($total * 1000) * 100) : 0;
            } else {
                $nilai = $skor;
            }

            DB::table('attempts')->where('id', $row->id)->update([
                'nilai' => max(0, min(100, $nilai)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'nilai']);
            $table->dropColumn('nilai');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('is_percobaan');
        });
    }
};
