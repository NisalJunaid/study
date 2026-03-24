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
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('mcq_options')->nullOnDelete();
            $table->longText('answer_text')->nullable();
            $table->boolean('is_correct')->nullable()->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->longText('feedback')->nullable();
            $table->string('grading_status', 20)->default('pending')->index();
            $table->json('ai_result_json')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'grading_status']);
            $table->index(['question_id', 'grading_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
