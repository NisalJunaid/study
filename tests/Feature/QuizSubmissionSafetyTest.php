<?php

namespace Tests\Feature;

use App\Jobs\GradeTheoryAnswerJob;
use App\Models\McqOption;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QuizSubmissionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_submit_requests_are_idempotent_and_safe(): void
    {
        Bus::fake();

        [$student, $quiz] = $this->makeMcqQuiz();

        $firstSubmitResponse = $this->actingAs($student)->post(route('student.quiz.submit', $quiz));
        $firstSubmitResponse->assertRedirect(route('student.quiz.results', $quiz));

        $firstSubmittedAt = $quiz->fresh()->submitted_at;

        $secondSubmitResponse = $this->actingAs($student)->post(route('student.quiz.submit', $quiz));
        $secondSubmitResponse->assertRedirect(route('student.quiz.results', $quiz));

        $quiz->refresh();

        $this->assertSame(Quiz::STATUS_GRADED, $quiz->status);
        $this->assertNotNull($quiz->submitted_at);
        $this->assertNotNull($quiz->graded_at);
        $this->assertTrue($quiz->submitted_at->equalTo($firstSubmittedAt));

        Bus::assertNotDispatched(GradeTheoryAnswerJob::class);
    }

    public function test_draft_answer_save_is_blocked_after_quiz_submission(): void
    {
        [$student, $quiz] = $this->makeMcqQuiz();
        $quizQuestion = $quiz->quizQuestions()->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz))
            ->assertRedirect(route('student.quiz.results', $quiz));

        $this->actingAs($student)
            ->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
                'selected_option_id' => data_get($quizQuestion->question_snapshot, 'options.0.id'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'locked');
    }

    public function test_grading_job_on_already_graded_quiz_does_not_mutate_answer_data(): void
    {
        [$quiz, $answer] = $this->makeTheoryQuizWithAnswer(
            quizStatus: Quiz::STATUS_GRADED,
            answerStatus: StudentAnswer::STATUS_GRADED,
            answerScore: 1.0,
            feedback: 'Already graded'
        );

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $quiz->refresh();
        $answer->refresh();

        $this->assertSame(Quiz::STATUS_GRADED, $quiz->status);
        $this->assertSame(StudentAnswer::STATUS_GRADED, $answer->grading_status);
        $this->assertSame('1.00', $answer->score);
        $this->assertSame('Already graded', $answer->feedback);
    }

    public function test_grading_job_on_processing_answer_does_not_corrupt_state(): void
    {
        [$quiz, $answer] = $this->makeTheoryQuizWithAnswer(
            quizStatus: Quiz::STATUS_GRADING,
            answerStatus: StudentAnswer::STATUS_PROCESSING,
            answerScore: null,
            feedback: null
        );

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $quiz->refresh();
        $answer->refresh();

        $this->assertSame(Quiz::STATUS_GRADING, $quiz->status);
        $this->assertSame(StudentAnswer::STATUS_PROCESSING, $answer->grading_status);
        $this->assertNull($answer->score);
        $this->assertNull($answer->feedback);
    }

    private function makeMcqQuiz(): array
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);
        $question = Question::factory()->mcq()->create(['subject_id' => $subject->id, 'marks' => 1]);
        $correctOption = McqOption::query()->create([
            'question_id' => $question->id,
            'option_key' => 'A',
            'option_text' => 'Correct',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now(),
            'last_interacted_at' => now(),
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => Question::TYPE_MCQ,
                'question_text' => 'Pick A',
                'options' => [[
                    'id' => $correctOption->id,
                    'option_key' => 'A',
                    'option_text' => 'Correct',
                    'is_correct' => true,
                ]],
            ],
            'max_score' => 1,
        ]);

        $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $student->id,
            'selected_option_id' => $correctOption->id,
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        return [$student, $quiz];
    }

    private function makeTheoryQuizWithAnswer(string $quizStatus, string $answerStatus, ?float $answerScore, ?string $feedback): array
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $question = Question::factory()->theory()->create(['subject_id' => $subject->id, 'marks' => 2]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => $quizStatus,
            'total_questions' => 1,
            'total_possible_score' => 2,
            'started_at' => now(),
            'submitted_at' => in_array($quizStatus, Quiz::submittedAttemptStatuses(), true) ? now() : null,
            'graded_at' => $quizStatus === Quiz::STATUS_GRADED ? now() : null,
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain something',
                'theory_meta' => [
                    'sample_answer' => 'Reference answer',
                    'max_score' => 2,
                ],
            ],
            'max_score' => 2,
            'requires_manual_review' => $answerStatus !== StudentAnswer::STATUS_GRADED,
        ]);

        $answer = $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'Student answer',
            'grading_status' => $answerStatus,
            'score' => $answerScore,
            'feedback' => $feedback,
            'graded_at' => in_array($answerStatus, [StudentAnswer::STATUS_GRADED, StudentAnswer::STATUS_OVERRIDDEN], true) ? now() : null,
        ]);

        return [$quiz, $answer];
    }
}
