<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->text('jawaban_teks')->nullable()->after('option_id');
            $table->timestamp('dinilai_at')->nullable()->after('skor');
            $table->unsignedBigInteger('dinilai_oleh')->nullable()->after('dinilai_at');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['jawaban_teks', 'dinilai_at', 'dinilai_oleh']);
        });
    }
};
