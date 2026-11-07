<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedInteger('poin_total')->default(0)->after('skor_total');
            $table->unsignedInteger('poin_maksimal')->default(0)->after('poin_total');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['poin_total', 'poin_maksimal']);
        });
    }
};
