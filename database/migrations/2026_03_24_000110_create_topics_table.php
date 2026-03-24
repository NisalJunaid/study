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
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['subject_id', 'name']);
            $table->unique(['subject_id', 'slug']);
            $table->index(['subject_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
