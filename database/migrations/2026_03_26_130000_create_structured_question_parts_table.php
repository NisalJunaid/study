<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structured_question_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('part_label', 20);
            $table->longText('prompt_text');
            $table->decimal('max_score', 5, 2)->default(1);
            $table->longText('sample_answer')->nullable();
            $table->longText('marking_notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'sort_order']);
            $table->unique(['question_id', 'part_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structured_question_parts');
    }
};
