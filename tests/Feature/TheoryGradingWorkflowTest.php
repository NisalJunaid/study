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
use App\Services\AI\TheoryGraderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
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

    public function test_theory_answers_are_queued_in_batches_on_quiz_submission(): void
    {
        config()->set('openai.batch_size', 2);

        Bus::fake();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);

        for ($i = 1; $i <= 3; $i++) {
            $theoryQuestion = Question::query()->create([
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain process #'.$i,
                'difficulty' => 'medium',
                'marks' => 3,
                'is_published' => true,
            ]);

            TheoryQuestionMeta::query()->create([
                'question_id' => $theoryQuestion->id,
                'sample_answer' => 'Reference answer #'.$i,
                'grading_notes' => 'Key points #'.$i,
                'keywords' => ['point-a', 'point-b'],
                'acceptable_phrases' => ['acceptable'],
                'max_score' => 3,
            ]);
        }

        $quiz = app(BuildQuizAction::class)->execute($student, [
            'subject_id' => $subject->id,
            'topic_ids' => [$topic->id],
            'mode' => Quiz::MODE_THEORY,
            'question_count' => 3,
            'difficulty' => null,
        ]);

        foreach ($quiz->quizQuestions as $quizQuestion) {
            $this->actingAs($student)
                ->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
                    'answer_text' => 'Student answer for question '.$quizQuestion->id,
                ])
                ->assertOk();
        }

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz))
            ->assertRedirect(route('student.quiz.results', $quiz));

        $quiz->refresh();
        $this->assertSame(Quiz::STATUS_GRADING, $quiz->status);

        Bus::assertDispatched(GradeTheoryAnswerJob::class, 2);
        Bus::assertDispatched(GradeTheoryAnswerJob::class, function (GradeTheoryAnswerJob $job) use ($quiz): bool {
            return $job->quizId === $quiz->id && count($job->studentAnswerIds) === 2;
        });
        Bus::assertDispatched(GradeTheoryAnswerJob::class, function (GradeTheoryAnswerJob $job) use ($quiz): bool {
            return $job->quizId === $quiz->id && count($job->studentAnswerIds) === 1;
        });
    }

    public function test_grading_job_saves_structured_batch_result_and_finalizes_quiz(): void
    {
        config()->set('openai.api_key', 'test-key');

        Http::fake([
            '*' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'results' => [
                                [
                                    'item_key' => '1',
                                    'verdict' => 'partially_correct',
                                    'score' => 2.25,
                                    'confidence' => 0.82,
                                    'matched_points' => ['sunlight used'],
                                    'missing_points' => ['no mention of oxygen'],
                                    'feedback' => 'Good start; include oxygen output.',
                                    'should_flag_for_review' => false,
                                ],
                            ],
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

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

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

    public function test_grading_job_falls_back_to_manual_review_on_malformed_batch_response(): void
    {
        config()->set('openai.api_key', 'test-key');

        Http::fake([
            '*' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode(['results' => []], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
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

        $question = Question::factory()->theory()->create(['subject_id' => $subject->id]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => 'theory',
                'question_text' => 'Explain osmosis.',
                'theory_meta' => ['sample_answer' => 'Reference', 'max_score' => 2],
            ],
            'max_score' => 2,
        ]);

        $answer = $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'test',
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $answer->refresh();
        $quiz->refresh();

        $this->assertSame(StudentAnswer::STATUS_MANUAL_REVIEW, $answer->grading_status);
        $this->assertNotNull($answer->graded_at);
        $this->assertSame(Quiz::STATUS_GRADED, $quiz->status);
    }

    public function test_theory_grader_service_reuses_cache_for_repeat_payloads(): void
    {
        config()->set('openai.api_key', 'test-key');
        config()->set('openai.enable_caching', true);
        Cache::flush();

        Http::fake([
            '*' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'results' => [[
                                'item_key' => '42',
                                'verdict' => 'correct',
                                'score' => 1,
                                'confidence' => 0.9,
                                'matched_points' => ['a'],
                                'missing_points' => [],
                                'feedback' => 'Good.',
                                'should_flag_for_review' => false,
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        $service = app(TheoryGraderService::class);

        $payload = [
            '42' => [
                'question' => 'Q',
                'student_answer' => 'A',
                'sample_answer' => 'S',
                'grading_notes' => '',
                'keywords' => ['x'],
                'acceptable_phrases' => [],
                'max_score' => 1,
            ],
        ];

        $first = $service->gradeBatch($payload);
        $second = $service->gradeBatch($payload);

        $this->assertSame('correct', $first['42']->verdict);
        $this->assertSame('correct', $second['42']->verdict);
        Http::assertSentCount(1);
    }
}
