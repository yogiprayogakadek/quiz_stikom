<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('locale', 5)->default('id');
            $table->boolean('allow_pin_enrollment')->default(true);
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('locale', 5)->nullable();
            $table->boolean('allow_pin_enrollment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn(['locale', 'allow_pin_enrollment']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['locale', 'allow_pin_enrollment']);
        });
    }
};
