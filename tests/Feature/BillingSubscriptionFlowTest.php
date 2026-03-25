<?php

namespace Tests\Feature;

use App\Models\DailyQuizUsage;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingSubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_new_user_gets_single_free_quiz_limited_to_ten_questions(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);
        Question::factory()->count(12)->create(['subject_id' => $subject->id, 'is_published' => true, 'topic_id' => null]);

        $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 10))
            ->assertRedirect();

        $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 11))
            ->assertRedirect(route('student.billing.index'));
    }

    public function test_trial_exhaustion_triggers_paywall_redirect(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);
        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now(),
        ]);

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);

        $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 5))
            ->assertRedirect(route('student.billing.index'));
    }

    public function test_payment_slip_upload_grants_temporary_access(): void
    {
        Storage::fake('local');

        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        $this->actingAs($student)
            ->post(route('student.billing.payments.store'), [
                'subscription_plan_id' => $plan->id,
                'slip' => UploadedFile::fake()->image('slip.jpg'),
            ])
            ->assertRedirect(route('student.billing.index'));

        $payment = SubscriptionPayment::query()->first();

        $this->assertNotNull($payment);
        $this->assertTrue($payment->temporaryAccessStillValid());
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $student->id,
            'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
        ]);
    }

    public function test_temporary_access_is_limited_to_six_quizzes_for_the_day(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);
        Question::factory()->count(20)->create(['subject_id' => $subject->id, 'is_published' => true, 'topic_id' => null]);
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
            'temporary_access_expires_at' => now()->addHours(24),
            'temporary_quiz_limit' => 6,
        ]);

        DailyQuizUsage::query()->create([
            'user_id' => $student->id,
            'subscription_payment_id' => $payment->id,
            'usage_date' => now()->toDateString(),
            'quiz_count' => 6,
        ]);

        $this->actingAs($student)
            ->post(route('student.quiz.store'), $this->quizPayload($subject, 5))
            ->assertRedirect(route('student.billing.index'));
    }

    public function test_temporary_access_expires_after_twenty_four_hours_if_unverified(): void
    {
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        $subscription = UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
            'billing_status' => UserSubscription::BILLING_INACTIVE,
        ]);

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
            'submitted_at' => now()->subDay(),
            'temporary_access_expires_at' => now()->subMinute(),
            'temporary_quiz_limit' => 6,
        ]);

        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertDatabaseHas('subscription_payments', ['status' => SubscriptionPayment::STATUS_EXPIRED]);
        $this->assertDatabaseHas('user_subscriptions', ['status' => UserSubscription::STATUS_SUSPENDED]);
    }

    public function test_admin_verification_unlocks_full_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->annualPlanData());
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
            'amount' => 100,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'billing/slips/a.jpg',
            'slip_original_name' => 'a.jpg',
            'submitted_at' => now(),
            'temporary_access_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.billing.payments.verify', $payment))
            ->assertRedirect();

        $this->assertDatabaseHas('subscription_payments', ['id' => $payment->id, 'status' => SubscriptionPayment::STATUS_VERIFIED]);
        $this->assertDatabaseHas('user_subscriptions', ['id' => $subscription->id, 'status' => UserSubscription::STATUS_ACTIVE]);
    }

    public function test_admin_rejection_removes_access_and_marks_subscription_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
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
            'temporary_access_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.billing.payments.reject', $payment), ['reason' => 'Unreadable slip'])
            ->assertRedirect();

        $this->assertDatabaseHas('subscription_payments', ['id' => $payment->id, 'status' => SubscriptionPayment::STATUS_REJECTED]);
        $this->assertDatabaseHas('user_subscriptions', ['id' => $subscription->id, 'status' => UserSubscription::STATUS_REJECTED]);
    }

    public function test_monthly_rule_suspends_after_third_if_unpaid(): void
    {
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $this->travelTo(now()->startOfMonth()->addDays(4));
        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertDatabaseHas('user_subscriptions', ['status' => UserSubscription::STATUS_SUSPENDED]);
    }

    public function test_annual_rule_suspends_after_expiry_plus_grace_period(): void
    {
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->annualPlanData());

        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => now()->subDays(4),
            'grace_ends_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertDatabaseHas('user_subscriptions', ['status' => UserSubscription::STATUS_SUSPENDED]);
    }

    public function test_admin_can_manage_plans_and_discounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.billing.plans.store'), [
                'code' => 'monthly-pro',
                'name' => 'Monthly Pro',
                'type' => 'monthly',
                'price' => 12,
                'currency' => 'USD',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.billing.plans.index'));

        $plan = SubscriptionPlan::query()->where('code', 'monthly-pro')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.billing.discounts.store'), [
                'subscription_plan_id' => $plan->id,
                'name' => 'Launch',
                'type' => 'percentage',
                'amount' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plan_discounts', [
            'subscription_plan_id' => $plan->id,
            'name' => 'Launch',
        ]);
    }

    private function quizPayload(Subject $subject, int $questionCount): array
    {
        return [
            'levels' => [$subject->level],
            'multi_subject_mode' => false,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'question_count' => $questionCount,
            'topic_ids' => [],
        ];
    }

    private function monthlyPlanData(): array
    {
        return [
            'code' => 'monthly-'.fake()->unique()->word(),
            'name' => 'Monthly',
            'type' => SubscriptionPlan::TYPE_MONTHLY,
            'price' => 10,
            'currency' => 'USD',
            'is_active' => true,
        ];
    }

    private function annualPlanData(): array
    {
        return [
            'code' => 'annual-'.fake()->unique()->word(),
            'name' => 'Annual',
            'type' => SubscriptionPlan::TYPE_ANNUAL,
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
