<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120);
            $table->string('pin', 12)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kelas_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'user_id']);
        });

        Schema::create('kelas_quiz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'quiz_id']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('quiz_id')
                ->constrained('kelas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelas_id');
        });

        Schema::dropIfExists('kelas_quiz');
        Schema::dropIfExists('kelas_user');
        Schema::dropIfExists('kelas');
    }
};
