<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('ai_monthly_requests')->nullable()->after('is_active');
            $table->unsignedInteger('storage_mb')->nullable()->after('ai_monthly_requests');
            $table->unsignedSmallInteger('admin_limit')->nullable()->after('storage_mb');
            $table->unsignedInteger('participant_limit')->nullable()->after('admin_limit');
            $table->unsignedSmallInteger('class_limit')->nullable()->after('participant_limit');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'ai_monthly_requests',
                'storage_mb',
                'admin_limit',
                'participant_limit',
                'class_limit',
            ]);
        });
    }
};
