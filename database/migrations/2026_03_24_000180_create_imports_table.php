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
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('status', 30)->default('uploaded')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->longText('error_summary')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['uploaded_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
