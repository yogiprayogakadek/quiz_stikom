<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('submitted');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
            $table->index(['status', 'responded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_feedbacks');
    }
};
