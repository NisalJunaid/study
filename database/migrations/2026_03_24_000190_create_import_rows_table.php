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
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_payload');
            $table->json('validation_errors')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('related_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
