<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Latest result of each review part per user; finishing a part again overwrites its row.
        Schema::create('lesson_review_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('part');
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedInteger('duration_seconds');
            $table->json('mistakes'); // ids of words answered wrong
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'part']);
            $table->index('lesson_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_review_results');
    }
};
