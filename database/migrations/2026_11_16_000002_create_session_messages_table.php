<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pengirim_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('teks', 500);
            $table->string('tipe', 20)->default('info');
            $table->timestamp('ditutup_at')->nullable();
            $table->timestamps();
            $table->index(['attempt_id', 'ditutup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_messages');
    }
};
