<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('status');
            $table->index(['organization_id', 'status', 'last_seen_at'], 'memberships_presence_index');
        });
    }

    public function down(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_presence_index');
            $table->dropColumn('last_seen_at');
        });
    }
};
