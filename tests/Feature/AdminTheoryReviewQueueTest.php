<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTheoryReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_review_queue_can_filter_actionable_states(): void
    {
        $admin = User::factory()->admin()->create();

        $aiFailed = $this->makeTheoryReviewAnswer('ai_failed');
        $lowConfidence = $this->makeTheoryReviewAnswer('low_confidence');
        $pendingManual = $this->makeTheoryReviewAnswer(null);

        $this->actingAs($admin)
            ->get(route('admin.theory-reviews.index', ['queue_state' => 'ai_failed']))
            ->assertOk()
            ->assertSee((string) $aiFailed->id)
            ->assertDontSee((string) $lowConfidence->id)
            ->assertDontSee((string) $pendingManual->id);

        $this->actingAs($admin)
            ->get(route('admin.theory-reviews.index', ['queue_state' => 'low_confidence']))
            ->assertOk()
            ->assertSee((string) $lowConfidence->id)
            ->assertDontSee((string) $aiFailed->id);
    }

    private function makeTheoryReviewAnswer(?string $manualReason): StudentAnswer
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 1,
            'total_possible_score' => 2,
            'started_at' => now(),
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        $question = Question::factory()->theory()->create(['subject_id' => $subject->id]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain osmosis.',
                'theory_meta' => ['sample_answer' => 'reference', 'max_score' => 2],
            ],
            'max_score' => 2,
            'requires_manual_review' => true,
        ]);

        return $quizQuestion->studentAnswer()->create([
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'Answer text',
            'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
            'ai_result_json' => $manualReason ? ['manual_review_reason' => $manualReason, 'error' => $manualReason === 'ai_failed' ? 'failure' : null] : null,
            'graded_at' => now(),
        ]);
    }
}
