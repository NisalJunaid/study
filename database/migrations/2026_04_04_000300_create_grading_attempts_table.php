<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('trigger', 20)->default('ai');
            $table->string('status', 40);
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('summary', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['student_answer_id', 'attempt_number']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_attempts');
    }
};
