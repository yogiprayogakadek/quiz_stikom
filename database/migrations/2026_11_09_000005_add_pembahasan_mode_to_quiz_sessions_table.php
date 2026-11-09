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
            $table->enum('pembahasan_mode', ['tersembunyi', 'otomatis', 'terbuka'])
                ->default('tersembunyi')
                ->after('tampilkan_pembahasan');
        });

        DB::table('quiz_sessions')
            ->where('tampilkan_pembahasan', true)
            ->update(['pembahasan_mode' => 'terbuka']);
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('pembahasan_mode');
        });
    }
};
