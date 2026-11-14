<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('petunjuk_json')->nullable();
            $table->json('sub_soal_json')->nullable();
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->json('jawaban_parts_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('jawaban_parts_json');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['petunjuk_json', 'sub_soal_json']);
        });
    }
};
