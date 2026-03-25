<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('billing_access_type', 30)->nullable()->after('status')->index();
            $table->foreignId('subscription_payment_id')->nullable()->after('billing_access_type')->constrained('subscription_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_payment_id');
            $table->dropColumn('billing_access_type');
        });
    }
};
