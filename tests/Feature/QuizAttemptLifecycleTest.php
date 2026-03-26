<?php

namespace Tests\Feature;

use App\Jobs\GradeTheoryAnswerJob;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\Quiz\QuizSessionCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QuizAttemptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_quiz_does_not_consume_free_trial_until_submitted(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now()->subMinutes(5),
            'last_interacted_at' => now()->subMinutes(1),
        ]);

        $this->assertTrue($student->fresh()->hasTrialRemaining());
    }

    public function test_cleanup_removes_only_unsubmitted_inactive_quizzes_and_answers(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $question = Question::factory()->theory()->create(['subject_id' => $subject->id]);

        $abandonedQuiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now()->subMinutes(40),
            'last_interacted_at' => now()->subMinutes(25),
        ]);

        $abandonedQuestion = QuizQuestion::query()->create([
            'quiz_id' => $abandonedQuiz->id,
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => ['type' => Question::TYPE_THEORY, 'question_text' => 'Q'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $abandonedQuestion->id,
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'Draft text',
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        $submittedQuiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'total_awarded_score' => 1,
            'started_at' => now()->subMinutes(40),
            'last_interacted_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(29),
            'graded_at' => now()->subMinutes(28),
        ]);

        $deleted = app(QuizSessionCleanupService::class)->cleanupAbandonedSessions(now(), 20);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('quizzes', ['id' => $abandonedQuiz->id]);
        $this->assertDatabaseMissing('quiz_questions', ['id' => $abandonedQuestion->id]);
        $this->assertDatabaseMissing('student_answers', ['quiz_question_id' => $abandonedQuestion->id]);
        $this->assertDatabaseHas('quizzes', ['id' => $submittedQuiz->id]);
    }

    public function test_theory_grading_jobs_are_not_dispatched_until_submit(): void
    {
        Bus::fake();

        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $question = Question::factory()->theory()->create(['subject_id' => $subject->id]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now(),
            'last_interacted_at' => now(),
        ]);

        $quizQuestion = QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain',
                'theory_meta' => ['sample_answer' => 'Ref', 'max_score' => 1],
            ],
            'max_score' => 1,
        ]);

        $this->actingAs($student)->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
            'answer_text' => 'Draft answer',
        ])->assertOk();

        Bus::assertNotDispatched(GradeTheoryAnswerJob::class);

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz))
            ->assertRedirect(route('student.quiz.results', $quiz));

        Bus::assertDispatched(GradeTheoryAnswerJob::class);
    }
}
