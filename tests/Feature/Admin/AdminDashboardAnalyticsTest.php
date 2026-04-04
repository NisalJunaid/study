<?php

namespace Tests\Feature\Admin;

use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_renders_operational_and_content_metrics_for_admins(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 10:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);

        $weakQuestion = Question::factory()->theory()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'is_published' => true,
            'moderation_flags' => [Question::FLAG_DUPLICATE_SUSPECTED],
        ]);

        Question::factory()->mcq()->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'is_published' => true,
            'moderation_flags' => [Question::FLAG_MISSING_EXPLANATION],
        ]);

        Question::factory()->mcq()->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'is_published' => false,
            'moderation_flags' => null,
        ]);

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now('UTC')->subDay(),
            'last_interacted_at' => now('UTC')->subDay(),
        ]);

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_SUBMITTED,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now('UTC')->subHours(6),
            'submitted_at' => now('UTC')->subHours(4),
        ]);

        $gradedQuiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 3,
            'total_possible_score' => 3,
            'total_awarded_score' => 1,
            'started_at' => now('UTC')->subHours(3),
            'submitted_at' => now('UTC')->subHours(2),
            'graded_at' => now('UTC')->subHour(),
        ]);

        foreach ([0.2, 0.5, 0.4] as $index => $score) {
            $quizQuestion = QuizQuestion::query()->create([
                'quiz_id' => $gradedQuiz->id,
                'question_id' => $weakQuestion->id,
                'order_no' => $index + 1,
                'question_snapshot' => ['type' => Question::TYPE_THEORY, 'question_text' => 'Q'],
                'max_score' => 1,
                'awarded_score' => $score,
            ]);

            StudentAnswer::query()->create([
                'quiz_question_id' => $quizQuestion->id,
                'question_id' => $weakQuestion->id,
                'user_id' => $student->id,
                'answer_text' => 'Response',
                'score' => $score,
                'grading_status' => StudentAnswer::STATUS_GRADED,
                'created_at' => now('UTC')->subHours(3),
            ]);
        }

        $pendingQuiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 2,
            'total_possible_score' => 2,
            'started_at' => now('UTC')->subHours(4),
            'submitted_at' => now('UTC')->subHours(2),
        ]);

        $pendingQuestion = QuizQuestion::query()->create([
            'quiz_id' => $pendingQuiz->id,
            'question_id' => $weakQuestion->id,
            'order_no' => 1,
            'question_snapshot' => ['type' => Question::TYPE_THEORY, 'question_text' => 'Pending'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $pendingQuestion->id,
            'question_id' => $weakQuestion->id,
            'user_id' => $student->id,
            'answer_text' => 'Pending answer',
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        $processingQuestion = QuizQuestion::query()->create([
            'quiz_id' => $pendingQuiz->id,
            'question_id' => $weakQuestion->id,
            'order_no' => 2,
            'question_snapshot' => ['type' => Question::TYPE_THEORY, 'question_text' => 'Processing'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $processingQuestion->id,
            'question_id' => $weakQuestion->id,
            'user_id' => $student->id,
            'answer_text' => 'Processing answer',
            'grading_status' => StudentAnswer::STATUS_PROCESSING,
        ]);

        $manualReviewQuiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now('UTC')->subDays(2),
            'submitted_at' => now('UTC')->subDays(2),
        ]);

        $manualReviewQuestion = QuizQuestion::query()->create([
            'quiz_id' => $manualReviewQuiz->id,
            'question_id' => $weakQuestion->id,
            'order_no' => 1,
            'question_snapshot' => ['type' => Question::TYPE_THEORY, 'question_text' => 'Review'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $manualReviewQuestion->id,
            'question_id' => $weakQuestion->id,
            'user_id' => $student->id,
            'answer_text' => 'Needs review',
            'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
            'created_at' => now('UTC')->subHours(40),
        ]);

        $import = Import::query()->create([
            'uploaded_by' => $admin->id,
            'file_name' => 'questions.csv',
            'file_path' => 'imports/questions.csv',
            'status' => Import::STATUS_COMPLETED,
        ]);

        ImportRow::query()->create([
            'import_id' => $import->id,
            'row_number' => 1,
            'raw_payload' => ['question_text' => 'Imported question'],
            'status' => ImportRow::STATUS_IMPORTED,
            'related_question_id' => $weakQuestion->id,
            'created_at' => now('UTC')->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Operational Dashboard')
            ->assertSee('Quizzes started')
            ->assertSee('1')
            ->assertSee('Quizzes submitted')
            ->assertSee('3')
            ->assertSee('Quizzes graded')
            ->assertSee('1')
            ->assertSee('Pending grading')
            ->assertSee('2')
            ->assertSee('Pending manual review')
            ->assertSee('1')
            ->assertSee('Active students')
            ->assertSee('1 in last 14 days')
            ->assertSee('Published questions')
            ->assertSee('2')
            ->assertSee('Unpublished questions')
            ->assertSee('1')
            ->assertSee('Flagged questions')
            ->assertSee('2')
            ->assertSee('Recently imported (7d)')
            ->assertSee('1')
            ->assertSee('Duplicate suspected')
            ->assertSee('1')
            ->assertSee('Mathematics · Algebra');
    }

    public function test_dashboard_metrics_are_forbidden_to_students(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
