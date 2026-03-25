<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('USD');
            $table->foreignId('discount_id')->nullable()->constrained('plan_discounts')->nullOnDelete();
            $table->json('discount_snapshot')->nullable();
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('status', 30)->default('pending')->index();
            $table->string('slip_path');
            $table->string('slip_original_name');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('temporary_access_expires_at')->nullable()->index();
            $table->unsignedInteger('temporary_quiz_limit')->default(6);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
