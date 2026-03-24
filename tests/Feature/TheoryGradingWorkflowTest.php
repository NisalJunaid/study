<?php

namespace Tests\Feature;

use App\Actions\Student\BuildQuizAction;
use App\Jobs\GradeTheoryAnswerJob;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TheoryGradingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_theory_answers_are_queued_on_quiz_submission(): void
    {
        Bus::fake();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);

        $theoryQuestion = Question::query()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_THEORY,
            'question_text' => 'Explain photosynthesis.',
            'difficulty' => 'medium',
            'marks' => 3,
            'is_published' => true,
        ]);

        TheoryQuestionMeta::query()->create([
            'question_id' => $theoryQuestion->id,
            'sample_answer' => 'Plants convert light into chemical energy.',
            'grading_notes' => 'Include sunlight, water and carbon dioxide.',
            'keywords' => ['sunlight', 'water', 'carbon dioxide'],
            'acceptable_phrases' => ['makes glucose'],
            'max_score' => 3,
        ]);

        $quiz = app(BuildQuizAction::class)->execute($student, [
            'subject_id' => $subject->id,
            'topic_ids' => [$topic->id],
            'mode' => Quiz::MODE_THEORY,
            'question_count' => 1,
            'difficulty' => null,
        ]);

        $quizQuestion = $quiz->quizQuestions()->firstOrFail();

        $this->actingAs($student)
            ->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
                'answer_text' => 'Plants use sunlight to make food.',
            ])
            ->assertOk();

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz))
            ->assertRedirect(route('student.quiz.results', $quiz));

        $quiz->refresh();

        $this->assertSame(Quiz::STATUS_GRADING, $quiz->status);

        Bus::assertDispatched(GradeTheoryAnswerJob::class, 1);
    }

    public function test_grading_job_saves_structured_result_and_finalizes_quiz(): void
    {
        config()->set('openai.api_key', 'test-key');

        Http::fake([
            '*' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'verdict' => 'partially_correct',
                            'score' => 2.25,
                            'confidence' => 0.82,
                            'matched_points' => ['sunlight used'],
                            'missing_points' => ['no mention of oxygen'],
                            'feedback' => 'Good start; include oxygen output.',
                            'should_flag_for_review' => false,
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 1,
            'total_possible_score' => 3,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $question = Question::factory()->theory()->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'question_text' => 'Explain photosynthesis.',
            'marks' => 3,
            'is_published' => true,
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => 'theory',
                'question_text' => 'Explain photosynthesis.',
                'theory_meta' => [
                    'sample_answer' => 'Plants make glucose from sunlight.',
                    'grading_notes' => 'Mention sunlight and carbon dioxide.',
                    'keywords' => ['sunlight', 'carbon dioxide'],
                    'acceptable_phrases' => ['make glucose'],
                    'max_score' => 3,
                ],
            ],
            'max_score' => 3,
            'requires_manual_review' => true,
        ]);

        $answer = $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'Plants use sunlight to make food.',
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        dispatch_sync(new GradeTheoryAnswerJob($answer->id));

        $answer->refresh();
        $quizQuestion->refresh();
        $quiz->refresh();

        $this->assertSame(StudentAnswer::STATUS_GRADED, $answer->grading_status);
        $this->assertSame('2.25', $answer->score);
        $this->assertNotEmpty($answer->ai_result_json);
        $this->assertSame('2.25', $quizQuestion->awarded_score);
        $this->assertFalse($quizQuestion->requires_manual_review);
        $this->assertSame(Quiz::STATUS_GRADED, $quiz->status);
        $this->assertSame('2.25', $quiz->total_awarded_score);
    }
}
