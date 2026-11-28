<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_soal_pg')->nullable()->after('jumlah_soal');
            $table->unsignedSmallInteger('jumlah_soal_essay')->nullable()->after('jumlah_soal_pg');
            $table->boolean('acak_soal')->default(true)->after('jumlah_soal_essay');
            $table->boolean('kelompokkan_tipe_soal')->default(false)->after('acak_soal');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan_tetap')->nullable()->after('bobot');
            $table->boolean('is_bonus')->default(false)->after('urutan_tetap');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['urutan_tetap', 'is_bonus']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn([
                'jumlah_soal_pg',
                'jumlah_soal_essay',
                'acak_soal',
                'kelompokkan_tipe_soal',
            ]);
        });
    }
};
