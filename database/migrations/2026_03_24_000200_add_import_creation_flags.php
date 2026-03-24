<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->boolean('allow_create_subjects')->default(false)->after('file_path');
            $table->boolean('allow_create_topics')->default(false)->after('allow_create_subjects');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['allow_create_subjects', 'allow_create_topics']);
        });
    }
};
