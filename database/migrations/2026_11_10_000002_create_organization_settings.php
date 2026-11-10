<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('site_name')->nullable();
            $table->string('site_tagline')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('login_title')->nullable();
            $table->text('login_description')->nullable();
            $table->string('login_layout', 12)->nullable();
            $table->string('ai_base_url')->nullable();
            $table->text('ai_api_key')->nullable();
            $table->string('ai_default_model')->nullable();
            $table->unsignedSmallInteger('ai_timeout')->nullable();
            $table->string('class_cover_mode', 10)->nullable();
            $table->string('image_base_url')->nullable();
            $table->text('image_api_key')->nullable();
            $table->string('image_model')->nullable();
            $table->timestamps();
        });

        $organizationId = DB::table('organizations')->orderBy('id')->value('id');
        $platform = Schema::hasTable('app_settings') ? DB::table('app_settings')->where('id', 1)->first() : null;
        if ($organizationId && $platform) {
            DB::table('organization_settings')->insert([
                'organization_id' => $organizationId,
                'site_name' => $platform->site_name,
                'site_tagline' => $platform->site_tagline,
                'organization_name' => $platform->organization_name,
                'primary_color' => $platform->primary_color,
                'logo_path' => $platform->logo_path,
                'login_title' => $platform->login_title,
                'login_description' => $platform->login_description,
                'login_layout' => $platform->login_layout,
                'ai_base_url' => $platform->ai_base_url,
                'ai_api_key' => $platform->ai_api_key,
                'ai_default_model' => $platform->ai_default_model,
                'ai_timeout' => $platform->ai_timeout,
                'class_cover_mode' => $platform->class_cover_mode,
                'image_base_url' => $platform->image_base_url,
                'image_api_key' => $platform->image_api_key,
                'image_model' => $platform->image_model,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
