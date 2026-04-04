<?php

namespace Tests\Feature;

use App\Actions\Student\FinalizeQuizGradingAction;
use App\Exceptions\TheoryGradingException;
use App\Jobs\GradeTheoryAnswerJob;
use App\Models\GradingAttempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\AI\TheoryGraderService;
use App\Support\DTOs\TheoryGradeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TheoryGradingResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_ai_success_path_records_attempt_and_grades_answer(): void
    {
        [$quiz, $answer] = $this->makeTheoryAnswer();

        $mock = Mockery::mock(TheoryGraderService::class);
        $mock->shouldReceive('gradeBatch')->once()->andReturn([
            (string) $answer->id => new TheoryGradeResult('correct', 2, 0.92, [], [], 'Great work', false, [
                'parsed' => ['score' => 2],
                'routing' => ['model' => 'gpt-4.1-mini'],
            ]),
        ]);
        $this->app->instance(TheoryGraderService::class, $mock);

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $answer->refresh();
        $this->assertSame(StudentAnswer::STATUS_GRADED, $answer->grading_status);
        $this->assertDatabaseHas('grading_attempts', [
            'student_answer_id' => $answer->id,
            'status' => 'graded',
            'trigger' => 'ai',
        ]);
    }

    public function test_transient_ai_failure_resets_answer_to_pending_for_retry(): void
    {
        config()->set('openai.retry_count', 2);

        [$quiz, $answer] = $this->makeTheoryAnswer();

        $mock = Mockery::mock(TheoryGraderService::class);
        $mock->shouldReceive('gradeBatch')->once()->andThrow(new TheoryGradingException('Rate limited', true, 429));
        $this->app->instance(TheoryGraderService::class, $mock);

        try {
            dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));
            $this->fail('Expected transient theory grading exception to be re-thrown for retry.');
        } catch (TheoryGradingException $exception) {
            $this->assertTrue($exception->retriable);
        }

        $answer->refresh();
        $this->assertSame(StudentAnswer::STATUS_PENDING, $answer->grading_status);
        $this->assertDatabaseHas('grading_attempts', [
            'student_answer_id' => $answer->id,
            'status' => 'retry_scheduled',
        ]);
    }

    public function test_permanent_ai_failure_falls_back_to_manual_review_and_logs_reason(): void
    {
        config()->set('openai.retry_count', 1);

        [$quiz, $answer] = $this->makeTheoryAnswer();

        $mock = Mockery::mock(TheoryGraderService::class);
        $mock->shouldReceive('gradeBatch')->once()->andThrow(new TheoryGradingException('Schema invalid', false));
        $this->app->instance(TheoryGraderService::class, $mock);

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $answer->refresh();
        $this->assertSame(StudentAnswer::STATUS_MANUAL_REVIEW, $answer->grading_status);
        $this->assertSame('ai_failed', data_get($answer->ai_result_json, 'manual_review_reason'));
        $this->assertDatabaseHas('grading_attempts', [
            'student_answer_id' => $answer->id,
            'status' => 'manual_review',
        ]);
    }

    public function test_low_confidence_result_requires_manual_review(): void
    {
        [$quiz, $answer] = $this->makeTheoryAnswer();

        $mock = Mockery::mock(TheoryGraderService::class);
        $mock->shouldReceive('gradeBatch')->once()->andReturn([
            (string) $answer->id => new TheoryGradeResult('partially_correct', 1, 0.41, [], [], 'Needs checks', true, [
                'parsed' => ['score' => 1, 'should_flag_for_review' => true],
                'routing' => ['model' => 'gpt-4.1-mini'],
            ]),
        ]);
        $this->app->instance(TheoryGraderService::class, $mock);

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $answer->refresh();
        $this->assertSame(StudentAnswer::STATUS_MANUAL_REVIEW, $answer->grading_status);
        $this->assertSame('low_confidence', data_get($answer->ai_result_json, 'manual_review_reason'));
    }

    public function test_quiz_aggregate_status_only_finishes_after_all_theory_answers_leave_pending_or_processing(): void
    {
        [$quiz, $firstAnswer] = $this->makeTheoryAnswer();
        $second = $this->appendTheoryAnswer($quiz);

        $firstAnswer->forceFill(['grading_status' => StudentAnswer::STATUS_GRADED, 'score' => 2])->save();
        $second->forceFill(['grading_status' => StudentAnswer::STATUS_PENDING])->save();

        app(FinalizeQuizGradingAction::class)->execute($quiz->fresh());
        $this->assertSame(Quiz::STATUS_GRADING, $quiz->fresh()->status);

        $second->forceFill(['grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW])->save();
        app(FinalizeQuizGradingAction::class)->execute($quiz->fresh());
        $this->assertSame(Quiz::STATUS_GRADED, $quiz->fresh()->status);
    }

    public function test_admin_override_is_audited_in_grading_attempts(): void
    {
        $admin = User::factory()->admin()->create();
        [$quiz, $answer] = $this->makeTheoryAnswer();

        $answer->forceFill([
            'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
            'score' => 0.5,
            'feedback' => 'Needs review',
        ])->save();

        $this->actingAs($admin)
            ->put(route('admin.theory-reviews.update', $answer), [
                'score' => 1.75,
                'feedback' => 'Improved after manual review.',
            ])
            ->assertRedirect(route('admin.theory-reviews.show', $answer));

        $answer->refresh();
        $this->assertSame(StudentAnswer::STATUS_OVERRIDDEN, $answer->grading_status);

        $overrideAttempt = GradingAttempt::query()
            ->where('student_answer_id', $answer->id)
            ->where('trigger', 'override')
            ->latest('id')
            ->first();

        $this->assertNotNull($overrideAttempt);
        $this->assertSame('overridden', $overrideAttempt->status);
        $this->assertSame('manual_review', data_get($overrideAttempt->meta, 'before.grading_status'));
        $this->assertSame('overridden', data_get($overrideAttempt->meta, 'after.grading_status'));
    }

    private function makeTheoryAnswer(): array
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 1,
            'total_possible_score' => 2,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $question = Question::factory()->theory()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Explain diffusion.',
            'marks' => 2,
            'is_published' => true,
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain diffusion.',
                'theory_meta' => [
                    'sample_answer' => 'Movement from high concentration to low concentration.',
                    'max_score' => 2,
                ],
            ],
            'max_score' => 2,
            'requires_manual_review' => true,
        ]);

        $answer = $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'test answer',
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        return [$quiz, $answer];
    }

    private function appendTheoryAnswer(Quiz $quiz): StudentAnswer
    {
        $question = Question::factory()->theory()->create([
            'subject_id' => $quiz->subject_id,
            'question_text' => 'Explain osmosis.',
            'marks' => 2,
            'is_published' => true,
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 2,
            'question_snapshot' => [
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain osmosis.',
                'theory_meta' => [
                    'sample_answer' => 'Water moves through a semipermeable membrane.',
                    'max_score' => 2,
                ],
            ],
            'max_score' => 2,
            'requires_manual_review' => true,
        ]);

        return $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $quiz->user_id,
            'answer_text' => 'pending answer',
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);
    }
}
