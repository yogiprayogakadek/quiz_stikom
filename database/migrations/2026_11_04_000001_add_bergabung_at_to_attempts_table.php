<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->timestamp('bergabung_at')->nullable();
        });

        // Attempt lama dianggap sudah bergabung supaya sesi yang sedang berjalan
        // tidak terhenti setelah pembaruan ini.
        DB::table('attempts')->update(['bergabung_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('bergabung_at');
        });
    }
};
