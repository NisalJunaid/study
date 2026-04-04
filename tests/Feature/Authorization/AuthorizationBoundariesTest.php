<?php

namespace Tests\Feature\Authorization;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubscriptionPayment;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationBoundariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_protected_student_pages(): void
    {
        $this->get(route('student.quiz.setup'))->assertRedirect(route('login'));
        $this->get(route('student.history.index'))->assertRedirect(route('login'));
        $this->get(route('student.progress.index'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_admin_manual_review_billing_or_import_pages(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('admin.theory-reviews.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.billing.plans.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.billing.settings.edit'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.imports.index'))->assertForbidden();
    }

    public function test_admin_can_access_admin_manual_review_billing_and_import_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.theory-reviews.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.billing.plans.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.imports.index'))->assertOk();
    }

    public function test_student_cannot_access_other_students_quiz_answers_or_results(): void
    {
        $owner = User::factory()->student()->create();
        $intruder = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $question = Question::factory()->mcq()->for($subject)->create([
            'topic_id' => null,
            'is_published' => true,
        ]);

        $quiz = Quiz::query()->create([
            'user_id' => $owner->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'total_awarded_score' => 1,
            'submitted_at' => now(),
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => Question::TYPE_MCQ,
                'question_text' => 'Owner only question',
            ],
            'max_score' => 1,
            'awarded_score' => 1,
        ]);

        $this->actingAs($intruder)
            ->get(route('student.quiz.take', $quiz))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('student.quiz.results', $quiz))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
                'selected_option_id' => null,
                'answer_text' => 'Invalid access',
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_download_another_students_billing_slip(): void
    {
        $owner = User::factory()->student()->create();
        $intruder = User::factory()->student()->create();

        $plan = SubscriptionPlan::query()->create([
            'code' => 'plan-authz',
            'name' => 'Authz Plan',
            'type' => SubscriptionPlan::TYPE_MONTHLY,
            'price' => 120,
            'currency' => 'USD',
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $payment = SubscriptionPayment::query()->create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $plan->id,
            'amount' => 120,
            'currency' => 'USD',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'slips/private-proof.png',
            'slip_original_name' => 'private-proof.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get(route('student.billing.payments.slip', $payment))
            ->assertForbidden();
    }
}
