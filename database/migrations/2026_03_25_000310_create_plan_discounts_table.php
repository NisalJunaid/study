<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->index();
            $table->string('type', 20);
            $table->decimal('amount', 10, 2);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['subscription_plan_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_discounts');
    }
};
