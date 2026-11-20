<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->json('exam_tools_json')->nullable()->after('is_percobaan');
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->json('exam_tools_json')->nullable()->after('clipboard_policy');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('exam_tools_json');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('exam_tools_json');
        });
    }
};
