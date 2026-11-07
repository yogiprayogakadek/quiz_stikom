<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('tipe', 20)->default('pilihan_ganda')->after('teks')->index();
            $table->unsignedSmallInteger('bobot')->default(1)->after('durasi_detik');
            $table->text('kunci_essay')->nullable()->after('pembahasan');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['tipe']);
            $table->dropColumn(['tipe', 'bobot', 'kunci_essay']);
        });
    }
};
