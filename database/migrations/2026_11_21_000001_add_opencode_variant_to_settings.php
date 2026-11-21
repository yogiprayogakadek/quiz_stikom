<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('opencode_variant', 50)->nullable()->after('opencode_model');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('opencode_variant', 50)->nullable()->after('opencode_model');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('opencode_variant');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('opencode_variant');
        });
    }
};
