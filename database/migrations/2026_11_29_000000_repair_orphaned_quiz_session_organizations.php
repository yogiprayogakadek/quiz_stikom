<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $orphanQuizIds = DB::table('quizzes')
            ->whereNull('organization_id')
            ->pluck('id');

        foreach ($orphanQuizIds as $quizId) {
            $sessionOrgIds = DB::table('quiz_sessions')
                ->where('quiz_id', $quizId)
                ->whereNotNull('organization_id')
                ->distinct()
                ->orderBy('organization_id')
                ->pluck('organization_id');

            if ($sessionOrgIds->count() >= 1) {
                DB::table('quizzes')
                    ->where('id', $quizId)
                    ->update(['organization_id' => (int) $sessionOrgIds->first()]);
            }
        }

        // Pulihkan kuis uji coba yang tertimpa organisasi lain oleh seeder versi lama.
        $trialSessions = DB::table('quiz_sessions')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_sessions.quiz_id')
            ->where('quiz_sessions.nama', 'Uji Coba Sistem')
            ->where('quizzes.judul', 'Kuis Uji Coba')
            ->whereNotNull('quiz_sessions.organization_id')
            ->where(function ($query) {
                $query->whereNull('quizzes.organization_id')
                    ->orWhereColumn('quizzes.organization_id', '!=', 'quiz_sessions.organization_id');
            })
            ->select(
                'quiz_sessions.id as session_id',
                'quiz_sessions.organization_id as session_org_id',
                'quizzes.id as quiz_id',
                'quizzes.organization_id as quiz_org_id'
            )
            ->get();

        foreach ($trialSessions as $row) {
            $otherSessionsInQuizOrg = $row->quiz_org_id !== null
                ? DB::table('quiz_sessions')
                    ->where('quiz_id', $row->quiz_id)
                    ->where('organization_id', $row->quiz_org_id)
                    ->exists()
                : false;

            if (! $otherSessionsInQuizOrg) {
                DB::table('quizzes')
                    ->where('id', $row->quiz_id)
                    ->update(['organization_id' => (int) $row->session_org_id]);
            } elseif ($row->quiz_org_id !== null) {
                DB::table('quiz_sessions')
                    ->where('id', $row->session_id)
                    ->update(['organization_id' => (int) $row->quiz_org_id]);
            }
        }

        $quizzesWithOrg = DB::table('quizzes')
            ->whereNotNull('organization_id')
            ->select('id', 'organization_id')
            ->get();

        foreach ($quizzesWithOrg as $quiz) {
            DB::table('questions')
                ->where('quiz_id', $quiz->id)
                ->where(function ($query) use ($quiz) {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', '!=', $quiz->organization_id);
                })
                ->update(['organization_id' => (int) $quiz->organization_id]);
        }
    }

    public function down(): void
    {
        // Perbaikan konsistensi data tidak dibalikkan.
    }
};
