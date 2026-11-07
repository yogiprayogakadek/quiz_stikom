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
            $table->foreignId('kelas_id')
                ->nullable()
                ->after('id')
                ->constrained('kelas')
                ->cascadeOnDelete();
        });

        // Backfill dari tabel pivot kelas_quiz jika ada
        if (Schema::hasTable('kelas_quiz')) {
            $pivot = DB::table('kelas_quiz')->get();
            foreach ($pivot as $row) {
                DB::table('quizzes')
                    ->where('id', $row->quiz_id)
                    ->whereNull('kelas_id')
                    ->update(['kelas_id' => $row->kelas_id]);
            }
        }

        // Jika masih ada kuis tanpa kelas, kaitkan ke kelas pertama atau buat kelas default
        $orphanCount = DB::table('quizzes')->whereNull('kelas_id')->count();
        if ($orphanCount > 0) {
            $kelasId = DB::table('kelas')->value('id');
            if (! $kelasId) {
                $adminId = DB::table('users')->where('role', 'admin')->value('id')
                    ?: DB::table('users')->value('id');

                $kelasId = DB::table('kelas')->insertGetId([
                    'nama' => 'Kelas Utama',
                    'pin' => '123456',
                    'created_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('quizzes')->whereNull('kelas_id')->update(['kelas_id' => $kelasId]);
        }
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelas_id');
        });
    }
};
