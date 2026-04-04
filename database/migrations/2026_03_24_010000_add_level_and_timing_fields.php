<?php

use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('level', 20)
                ->default(Subject::LEVEL_O)
                ->after('slug')
                ->index();
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->string('level', 20)
                ->default(Subject::LEVEL_O)
                ->after('user_id')
                ->index();
        });

        Schema::table('student_answers', function (Blueprint $table): void {
            $table->timestamp('question_started_at')->nullable()->after('ai_result_json');
            $table->timestamp('answered_at')->nullable()->after('question_started_at');
            $table->unsignedInteger('ideal_time_seconds')->nullable()->after('answered_at');
            $table->unsignedInteger('answer_duration_seconds')->nullable()->after('ideal_time_seconds');
            $table->boolean('answered_on_time')->nullable()->after('answer_duration_seconds');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::table('quizzes')->update([
                'level' => DB::raw('(SELECT COALESCE(subjects.level, "o_level") FROM subjects WHERE subjects.id = quizzes.subject_id)'),
            ]);

            DB::table('quizzes')
                ->whereNull('level')
                ->update(['level' => Subject::LEVEL_O]);

            return;
        }

        DB::table('quizzes')
            ->leftJoin('subjects', 'subjects.id', '=', 'quizzes.subject_id')
            ->update([
                'quizzes.level' => DB::raw('COALESCE(subjects.level, "o_level")'),
            ]);
    }

    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table): void {
            $table->dropColumn([
                'question_started_at',
                'answered_at',
                'ideal_time_seconds',
                'answer_duration_seconds',
                'answered_on_time',
            ]);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn('level');
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('level');
        });
    }
};
