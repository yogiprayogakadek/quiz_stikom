<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->boolean('nilai_ditampilkan')->default(false)->after('pembahasan_mode');
        });

        DB::table('quiz_sessions')
            ->where('status', 'selesai')
            ->update(['nilai_ditampilkan' => true]);
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('nilai_ditampilkan');
        });
    }
};
