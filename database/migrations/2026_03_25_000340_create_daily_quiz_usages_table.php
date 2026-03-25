<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quiz_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('usage_date')->index();
            $table->unsignedInteger('quiz_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'subscription_payment_id', 'usage_date'], 'daily_usage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quiz_usages');
    }
};
