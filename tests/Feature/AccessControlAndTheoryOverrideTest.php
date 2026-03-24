<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlAndTheoryOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_student_and_admin_route_access_is_properly_restricted(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('student.dashboard'))
            ->assertForbidden();
    }

    public function test_student_can_create_quiz_from_builder_route_and_theory_can_be_overridden_by_admin(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $subject = Subject::factory()->create(['is_active' => true]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);

        $theoryQuestion = Question::query()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_THEORY,
            'question_text' => 'Explain osmosis.',
            'difficulty' => 'medium',
            'marks' => 4,
            'is_published' => true,
        ]);

        TheoryQuestionMeta::query()->create([
            'question_id' => $theoryQuestion->id,
            'sample_answer' => 'Movement of water from high to low concentration through a semipermeable membrane.',
            'max_score' => 4,
        ]);

        $this->actingAs($student)
            ->post(route('student.quiz.store'), [
                'subject_id' => $subject->id,
                'topic_ids' => [$topic->id],
                'mode' => Quiz::MODE_THEORY,
                'question_count' => 1,
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->firstOrFail();
        $quizQuestion = $quiz->quizQuestions()->firstOrFail();

        $this->actingAs($student)
            ->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
                'answer_text' => 'Water moves across a membrane to balance concentration.',
            ])
            ->assertOk();

        // Simulate grading completion that still requires manual review.
        $answer = StudentAnswer::query()->firstOrFail();
        $answer->forceFill([
            'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
            'score' => 1,
            'feedback' => 'Needs review.',
        ])->save();

        $quizQuestion->forceFill([
            'awarded_score' => 1,
            'requires_manual_review' => true,
        ])->save();

        $quiz->forceFill([
            'status' => Quiz::STATUS_GRADING,
            'submitted_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->put(route('admin.theory-reviews.update', $answer), [
                'score' => 3.5,
                'feedback' => 'Good answer, add mention of semipermeable membrane.',
            ])
            ->assertRedirect(route('admin.theory-reviews.show', $answer));

        $answer->refresh();
        $quizQuestion->refresh();
        $quiz->refresh();

        $this->assertSame(StudentAnswer::STATUS_OVERRIDDEN, $answer->grading_status);
        $this->assertSame('3.50', $answer->score);
        $this->assertFalse($quizQuestion->requires_manual_review);
        $this->assertSame(Quiz::STATUS_GRADED, $quiz->status);
    }
}
