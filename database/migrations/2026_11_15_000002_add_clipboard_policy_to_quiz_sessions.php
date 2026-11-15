<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('clipboard_policy', 20)->default('monitor')->after('pelanggaran_penalty_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('clipboard_policy');
        });
    }
};
