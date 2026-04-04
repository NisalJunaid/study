<?php

namespace Tests\Feature\Billing;

use App\Models\DailyQuizUsage;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Billing\QuizAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAccessDecisionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_active_subscription_student_can_start_quiz(): void
    {
        $student = User::factory()->student()->create([
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);

        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $subject = Subject::factory()->create(['is_active' => true]);
        Question::factory()->mcq()->for($subject)->create([
            'topic_id' => null,
            'is_published' => true,
        ]);

        $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 1))
            ->assertRedirectContains('/quiz/');

        $this->assertDatabaseCount('quizzes', 1);
    }

    public function test_pending_verification_student_can_be_blocked_with_daily_limit_message(): void
    {
        $student = User::factory()->student()->create([
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        $subscription = UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
            'billing_status' => UserSubscription::BILLING_INACTIVE,
        ]);

        $payment = SubscriptionPayment::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'billing/slips/a.jpg',
            'slip_original_name' => 'a.jpg',
            'submitted_at' => now(),
            'temporary_access_expires_at' => now()->addHours(2),
            'temporary_quiz_limit' => 6,
        ]);

        DailyQuizUsage::query()->create([
            'user_id' => $student->id,
            'subscription_payment_id' => $payment->id,
            'usage_date' => now()->toDateString(),
            'quiz_count' => 6,
        ]);

        $subject = Subject::factory()->create(['is_active' => true]);
        Question::factory()->mcq()->for($subject)->create(['topic_id' => null, 'is_published' => true]);

        $response = $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 1));

        $response->assertRedirect(route('student.billing.subscription'));
        $response->assertSessionHas('overlay', fn (array $overlay) => str_contains(strtolower((string) ($overlay['message'] ?? '')), 'daily temporary access limit reached'));
    }

    public function test_temporary_access_active_vs_expired_states(): void
    {
        $student = User::factory()->student()->create([
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        $subscription = UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
            'billing_status' => UserSubscription::BILLING_INACTIVE,
        ]);

        $service = app(QuizAccessService::class);

        SubscriptionPayment::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'billing/slips/a.jpg',
            'slip_original_name' => 'a.jpg',
            'submitted_at' => now(),
            'temporary_access_expires_at' => now()->addHours(4),
            'temporary_quiz_limit' => 6,
        ]);

        $allowed = $service->canStartQuiz($student->fresh(), 1);
        $this->assertTrue($allowed['allowed']);
        $this->assertSame(QuizAccessService::REASON_PENDING_VERIFICATION, $allowed['reason']);

        $student->payments()->delete();

        SubscriptionPayment::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'billing/slips/b.jpg',
            'slip_original_name' => 'b.jpg',
            'submitted_at' => now()->subDay(),
            'temporary_access_expires_at' => now()->subMinute(),
            'temporary_quiz_limit' => 6,
        ]);

        $blocked = $service->canStartQuiz($student->fresh(), 1);
        $this->assertFalse($blocked['allowed']);
        $this->assertSame(QuizAccessService::REASON_TEMPORARY_ACCESS_EXPIRED, $blocked['reason']);
    }

    public function test_daily_quota_is_enforced(): void
    {
        $student = User::factory()->student()->create([
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        $subscription = UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
            'billing_status' => UserSubscription::BILLING_INACTIVE,
        ]);

        $payment = SubscriptionPayment::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'billing/slips/a.jpg',
            'slip_original_name' => 'a.jpg',
            'submitted_at' => now(),
            'temporary_access_expires_at' => now()->addHours(2),
            'temporary_quiz_limit' => 6,
        ]);

        DailyQuizUsage::query()->create([
            'user_id' => $student->id,
            'subscription_payment_id' => $payment->id,
            'usage_date' => now()->toDateString(),
            'quiz_count' => 5,
        ]);

        $service = app(QuizAccessService::class);
        $allowed = $service->canStartQuiz($student, 1);
        $this->assertTrue($allowed['allowed']);
        $this->assertSame(1, $allowed['remaining_daily_quota']);

        DailyQuizUsage::query()->where('id', '>', 0)->update(['quiz_count' => 6]);

        $blocked = $service->canStartQuiz($student->fresh(), 1);
        $this->assertFalse($blocked['allowed']);
        $this->assertSame(QuizAccessService::REASON_DAILY_LIMIT_REACHED, $blocked['reason']);
    }

    public function test_admin_bypass_is_explicitly_allowed(): void
    {
        $admin = User::factory()->admin()->create();

        $decision = app(QuizAccessService::class)->canStartQuiz($admin, 99);

        $this->assertTrue($decision['allowed']);
        $this->assertSame(QuizAccessService::ACCESS_ADMIN_BYPASS, $decision['access_type']);
    }

    public function test_missing_billing_configuration_fails_closed_without_crashing(): void
    {
        $student = User::factory()->student()->create([
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);

        $subject = Subject::factory()->create(['is_active' => true]);
        Question::factory()->mcq()->for($subject)->create([
            'topic_id' => null,
            'is_published' => true,
        ]);

        $response = $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 1));

        $response->assertRedirect(route('student.billing.subscription'));
        $response->assertSessionHas('overlay', fn (array $overlay) => str_contains(strtolower((string) ($overlay['message'] ?? '')), 'billing is temporarily unavailable'));
    }

    private function quizPayload(Subject $subject, int $questionCount): array
    {
        return [
            'levels' => [$subject->level],
            'multi_subject_mode' => false,
            'subject_id' => $subject->id,
            'mode' => 'mcq',
            'question_count' => $questionCount,
        ];
    }

    private function monthlyPlanData(): array
    {
        return [
            'code' => 'monthly-'.uniqid(),
            'name' => 'Monthly',
            'type' => SubscriptionPlan::TYPE_MONTHLY,
            'price' => 10,
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
