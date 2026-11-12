<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('nama', 80);
            $table->string('file_path', 255);
            $table->string('berat', 10)->default('400');
            $table->string('gaya', 10)->default('normal');
            $table->string('format', 10);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'nama']);
            $table->unique(['organization_id', 'nama', 'berat', 'gaya']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('font_utama_id')->nullable()->after('primary_color');
            $table->unsignedBigInteger('font_judul_id')->nullable()->after('font_utama_id');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('font_utama_id')->nullable()->after('primary_color');
            $table->unsignedBigInteger('font_judul_id')->nullable()->after('font_utama_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn(['font_utama_id', 'font_judul_id']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['font_utama_id', 'font_judul_id']);
        });

        Schema::dropIfExists('fonts');
    }
};
