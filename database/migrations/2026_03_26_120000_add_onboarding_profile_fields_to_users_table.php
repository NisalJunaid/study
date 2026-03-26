<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_document_number', 100)->nullable()->after('onboarding_intent');
            $table->string('nationality', 100)->nullable()->after('id_document_number');
            $table->string('contact_number', 40)->nullable()->after('nationality');
            $table->string('id_document_path')->nullable()->after('contact_number');
            $table->string('id_document_original_name')->nullable()->after('id_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'id_document_number',
                'nationality',
                'contact_number',
                'id_document_path',
                'id_document_original_name',
            ]);
        });
    }
};
