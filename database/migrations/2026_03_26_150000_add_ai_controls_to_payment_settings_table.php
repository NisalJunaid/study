<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->unsignedInteger('daily_ai_credits')->default(50)->after('registration_fee');
            $table->unsignedTinyInteger('mixed_quiz_ai_weight_percentage')->default(50)->after('daily_ai_credits');
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_ai_credits', 'mixed_quiz_ai_weight_percentage']);
        });
    }
};

