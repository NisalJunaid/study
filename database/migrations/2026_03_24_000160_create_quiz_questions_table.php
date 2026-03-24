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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('order_no');
            $table->json('question_snapshot');
            $table->decimal('max_score', 5, 2)->default(1);
            $table->decimal('awarded_score', 5, 2)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->boolean('requires_manual_review')->default(false)->index();
            $table->timestamps();

            $table->unique(['quiz_id', 'order_no']);
            $table->index(['quiz_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
