<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('inactive')->index();
            $table->string('billing_status', 20)->default('inactive')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable()->index();
            $table->timestamp('suspended_at')->nullable()->index();
            $table->text('suspended_reason')->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
