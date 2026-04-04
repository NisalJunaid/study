<?php

namespace Tests\Unit\Services;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\Analytics\StudentProgressAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProgressAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentProgressAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StudentProgressAnalyticsService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_calculates_current_and_longest_streaks_from_submitted_quizzes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 10:00:00', 'UTC'));

        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        foreach (['2026-04-04 08:00:00', '2026-04-03 09:00:00', '2026-04-02 11:00:00', '2026-03-30 10:00:00'] as $submittedAt) {
            Quiz::query()->create([
                'user_id' => $student->id,
                'subject_id' => $subject->id,
                'mode' => Quiz::MODE_MCQ,
                'status' => Quiz::STATUS_GRADED,
                'total_questions' => 4,
                'total_possible_score' => 4,
                'total_awarded_score' => 3,
                'submitted_at' => $submittedAt,
            ]);
        }

        $summary = $this->service->summarize($student);

        $this->assertSame(3, $summary['streak']['current']);
        $this->assertSame(3, $summary['streak']['longest']);
        $this->assertTrue($summary['streak']['active_today']);
    }

    public function test_it_calculates_daily_goal_progress_from_submitted_quizzes_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 10:00:00', 'UTC'));

        $student = User::factory()->student()->create(['daily_quiz_goal' => 3]);
        $subject = Subject::factory()->create();

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_SUBMITTED,
            'total_questions' => 5,
            'total_possible_score' => 5,
            'total_awarded_score' => 4,
            'submitted_at' => '2026-04-04 03:00:00',
        ]);

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 5,
            'total_possible_score' => 5,
            'total_awarded_score' => 4,
            'submitted_at' => '2026-04-04 07:00:00',
        ]);

        $summary = $this->service->summarize($student);

        $this->assertSame(3, $summary['daily_goal']['goal']);
        $this->assertSame(2, $summary['daily_goal']['completed_today']);
        $this->assertSame(1, $summary['daily_goal']['remaining']);
        $this->assertSame(67, $summary['daily_goal']['progress_percentage']);
        $this->assertFalse($summary['daily_goal']['is_met']);
    }

    public function test_it_generates_weak_topic_recommendation_from_existing_performance_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 10:00:00', 'UTC'));

        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);
        $question = Question::factory()->theory()->create(['subject_id' => $subject->id, 'topic_id' => $topic->id]);

        $quizOne = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 1,
            'total_possible_score' => 4,
            'total_awarded_score' => 1,
            'submitted_at' => '2026-04-02 09:00:00',
        ]);

        $quizTwo = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 1,
            'total_possible_score' => 4,
            'total_awarded_score' => 1,
            'submitted_at' => '2026-04-03 09:00:00',
        ]);

        $this->createAnswer($quizOne, $question, $student->id, 0.8);
        $this->createAnswer($quizTwo, $question, $student->id, 1.0);

        $summary = $this->service->summarize($student);

        $this->assertSame('weak_topics', $summary['recommendations']['strategy']);
        $this->assertSame(['Algebra'], $summary['recommendations']['topic_names']);
        $this->assertSame($subject->id, $summary['recommendations']['quiz_setup_params']['subject_id']);
        $this->assertSame([$topic->id], $summary['recommendations']['quiz_setup_params']['topic_ids']);
    }

    public function test_it_falls_back_to_mixed_revision_when_student_has_no_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 10:00:00', 'UTC'));

        $student = User::factory()->student()->create();

        $summary = $this->service->summarize($student);

        $this->assertSame(0, $summary['streak']['current']);
        $this->assertSame(0, $summary['daily_goal']['completed_today']);
        $this->assertSame('mixed_revision', $summary['recommendations']['strategy']);
    }

    private function createAnswer(Quiz $quiz, Question $question, int $userId, float $score): void
    {
        $quizQuestion = QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => $question->type,
                'question_text' => $question->question_text,
            ],
            'max_score' => 4,
            'awarded_score' => $score,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $quizQuestion->id,
            'question_id' => $question->id,
            'user_id' => $userId,
            'answer_text' => 'Example',
            'score' => $score,
            'grading_status' => StudentAnswer::STATUS_GRADED,
        ]);
    }
}
