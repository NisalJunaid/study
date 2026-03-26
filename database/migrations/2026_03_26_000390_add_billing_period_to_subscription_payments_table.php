<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->date('billing_period_start')->nullable()->after('currency')->index();
            $table->date('billing_period_end')->nullable()->after('billing_period_start')->index();
            $table->json('pricing_snapshot')->nullable()->after('discount_snapshot');
            $table->index(['user_id', 'subscription_plan_id', 'status', 'billing_period_start'], 'sub_pay_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex('sub_pay_cycle_idx');
            $table->dropColumn(['billing_period_start', 'billing_period_end', 'pricing_snapshot']);
        });
    }
};
