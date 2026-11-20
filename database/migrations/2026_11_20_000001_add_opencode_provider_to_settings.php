<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('ai_provider', 16)->default('openai')->after('login_layout');
            $table->string('opencode_base_url')->nullable()->after('ai_timeout');
            $table->string('opencode_username', 100)->nullable()->after('opencode_base_url');
            $table->text('opencode_password')->nullable()->after('opencode_username');
            $table->string('opencode_model', 150)->nullable()->after('opencode_password');
            $table->unsignedSmallInteger('opencode_timeout')->nullable()->after('opencode_model');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('ai_provider', 16)->nullable()->after('login_layout');
            $table->string('opencode_base_url')->nullable()->after('ai_timeout');
            $table->string('opencode_username', 100)->nullable()->after('opencode_base_url');
            $table->text('opencode_password')->nullable()->after('opencode_username');
            $table->string('opencode_model', 150)->nullable()->after('opencode_password');
            $table->unsignedSmallInteger('opencode_timeout')->nullable()->after('opencode_model');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'opencode_base_url',
                'opencode_username',
                'opencode_password',
                'opencode_model',
                'opencode_timeout',
            ]);
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'opencode_base_url',
                'opencode_username',
                'opencode_password',
                'opencode_model',
                'opencode_timeout',
            ]);
        });
    }
};
