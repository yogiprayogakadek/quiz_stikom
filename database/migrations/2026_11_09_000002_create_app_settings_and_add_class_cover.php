<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('STIKOM Quiz');
            $table->string('site_tagline')->default('Kuis kelas yang cepat dan terpantau');
            $table->string('organization_name')->nullable();
            $table->string('primary_color', 7)->default('#2563eb');
            $table->string('logo_path')->nullable();
            $table->string('login_title')->default('Masuk ke ruang kuis');
            $table->text('login_description')->nullable();
            $table->string('login_layout', 12)->default('split');
            $table->string('ai_base_url')->nullable();
            $table->text('ai_api_key')->nullable();
            $table->string('ai_default_model')->nullable();
            $table->unsignedSmallInteger('ai_timeout')->default(60);
            $table->string('class_cover_mode', 10)->default('local');
            $table->string('image_base_url')->nullable();
            $table->text('image_api_key')->nullable();
            $table->string('image_model')->nullable();
            $table->timestamps();
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('pin');
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });

        Schema::dropIfExists('app_settings');
    }
};
