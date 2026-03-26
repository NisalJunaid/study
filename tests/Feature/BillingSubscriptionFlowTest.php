<?php

namespace Tests\Feature;

use App\Models\DailyQuizUsage;
use App\Models\PaymentSetting;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
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


    public function test_subscription_page_defaults_to_concise_overview_for_active_subscription(): void
    {
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($student)
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertSee('Current subscription')
            ->assertSee('Next due / renewal')
            ->assertDontSee('Payment initiation')
            ->assertDontSee('Continue to payment');
    }

    public function test_payment_guided_steps_are_only_shown_after_payment_initiation(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00'));
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        PaymentSetting::query()->create([
            'bank_account_name' => 'Focus Lab',
            'bank_account_number' => '1234567890',
            'currency' => 'USD',
        ]);

        $this->actingAs($student)
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertSee('Current subscription')
            ->assertDontSee('Payment initiation')
            ->assertDontSee('Continue to payment');

        $this->actingAs($student)
            ->get(route('student.billing.subscription', ['start_payment' => 1]))
            ->assertOk()
            ->assertSee('Payment initiation')
            ->assertSee('Step 3: Review and continue');

        $this->actingAs($student)
            ->post(route('student.billing.subscription.select-plan'), [
                'subscription_plan_id' => $plan->id,
            ])
            ->assertRedirect(route('student.billing.payment'));

        $this->actingAs($student)
            ->get(route('student.billing.payment'))
            ->assertOk()
            ->assertSee('Payment progress')
            ->assertSee('Step 4: Confirm submission');
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
            ->assertRedirect(route('student.billing.subscription'))
            ->assertSessionHas('overlay', fn (array $overlay) => ($overlay['primary_label'] ?? null) === 'Choose a Plan');
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
            ->assertRedirect(route('student.billing.subscription'))
            ->assertSessionHas('overlay', fn (array $overlay) => ($overlay['variant'] ?? null) === 'warning');
    }

    public function test_payment_slip_upload_grants_temporary_access(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00'));

        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        $this->actingAs($student)
            ->post(route('student.billing.payments.store'), [
                'subscription_plan_id' => $plan->id,
                'slip' => UploadedFile::fake()->image('slip.jpg'),
            ])
            ->assertRedirect(route('student.billing.subscription'))
            ->assertSessionHas('overlay', fn (array $overlay) => ($overlay['redirect_url'] ?? null) === route('student.quiz.setup'));

        $payment = SubscriptionPayment::query()->first();

        $this->assertNotNull($payment);
        $this->assertTrue($payment->temporaryAccessStillValid());
        $this->assertSame('2026-03-01', $payment->billing_period_start?->toDateString());
        $this->assertSame('2026-03-31', $payment->billing_period_end?->toDateString());
        $this->assertSame(7.33, (float) $payment->pricing_snapshot['prorated_plan_amount']);
        $this->assertSame(7.33, (float) $payment->amount);
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
            ->assertRedirect(route('student.billing.subscription'))
            ->assertSessionHas('overlay');
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
        $this->travelTo(Carbon::parse('2026-04-04 08:00:00'));
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => Carbon::parse('2026-04-03 23:59:59'),
        ]);

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


    public function test_subscription_page_renders_monthly_and_annual_toggle_controls(): void
    {
        $student = User::factory()->student()->create();
        SubscriptionPlan::query()->create($this->monthlyPlanData());
        SubscriptionPlan::query()->create($this->annualPlanData());

        $this->actingAs($student)
            ->get(route('student.billing.subscription', ['start_payment' => 1]))
            ->assertOk()
            ->assertSee('Monthly')
            ->assertSee('Annual')
            ->assertSee('Continue to payment');
    }

    public function test_selected_plan_is_used_on_payment_page_with_bank_details(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00'));
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        PaymentSetting::query()->create([
            'bank_account_name' => 'Focus Lab',
            'bank_account_number' => '1234567890',
            'bank_name' => 'Demo Bank',
            'currency' => 'USD',
            'payment_instructions' => 'Upload clear transfer slip.',
        ]);

        $this->actingAs($student)
            ->post(route('student.billing.subscription.select-plan'), [
                'subscription_plan_id' => $plan->id,
            ])
            ->assertRedirect(route('student.billing.payment'));

        $this->actingAs($student)
            ->get(route('student.billing.payment'))
            ->assertOk()
            ->assertSee('Amount due')
            ->assertSee('1234567890')
            ->assertSee('Prorated plan amount');
    }

    public function test_suspended_user_is_redirected_to_billing_routes_only(): void
    {
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_SUSPENDED,
            'billing_status' => UserSubscription::BILLING_SUSPENDED,
            'suspended_reason' => 'Payment overdue.',
        ]);

        $this->actingAs($student)
            ->get(route('student.history.index'))
            ->assertRedirect(route('student.billing.subscription'))
            ->assertSessionHas('overlay', fn (array $overlay) => ($overlay['primary_label'] ?? null) === 'Resolve Subscription');

        $this->actingAs($student)
            ->get(route('student.billing.subscription'))
            ->assertOk();
    }

    public function test_admin_can_update_bank_account_details(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.billing.settings.update'), [
                'bank_account_name' => 'Focus Lab Collections',
                'bank_account_number' => '4444333322',
                'bank_name' => 'Scholars Bank',
                'currency' => 'USD',
                'registration_fee' => 12,
                'payment_instructions' => 'Reference your email in transfer note.',
            ])
            ->assertRedirect(route('admin.billing.settings.edit'));

        $this->assertDatabaseHas('payment_settings', [
            'bank_account_number' => '4444333322',
            'bank_name' => 'Scholars Bank',
            'registration_fee' => 12,
        ]);
    }

    public function test_active_monthly_before_24th_cannot_pay_monthly_again(): void
    {
        $this->travelTo(Carbon::parse('2026-03-20 09:00:00'));
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'activated_at' => Carbon::parse('2026-03-01'),
            'expires_at' => Carbon::parse('2026-04-03 23:59:59'),
        ]);

        $this->actingAs($student)
            ->get(route('student.billing.subscription'))
            ->assertSee('Renewal opens on Mar 24, 2026.');
    }

    public function test_active_monthly_on_or_after_24th_can_initiate_next_month_payment(): void
    {
        $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => Carbon::parse('2026-04-03 23:59:59'),
        ]);

        $this->actingAs($student)
            ->post(route('student.billing.subscription.select-plan'), ['subscription_plan_id' => $plan->id])
            ->assertRedirect(route('student.billing.payment'));

        $this->actingAs($student)
            ->get(route('student.billing.payment'))
            ->assertSee('Apr 01, 2026 - Apr 30, 2026');
    }

    public function test_pending_verification_for_renewal_suppresses_duplicate_payment_option(): void
    {
        $this->travelTo(Carbon::parse('2026-03-25 09:00:00'));
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());
        $subscription = UserSubscription::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_status' => UserSubscription::BILLING_ACTIVE,
            'expires_at' => Carbon::parse('2026-04-03 23:59:59'),
        ]);
        SubscriptionPayment::query()->create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'USD',
            'billing_period_start' => '2026-04-01',
            'billing_period_end' => '2026-04-30',
            'payment_method' => 'bank_transfer',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'slip_path' => 'billing/slips/a.jpg',
            'slip_original_name' => 'a.jpg',
            'submitted_at' => now(),
            'temporary_access_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($student)
            ->get(route('student.billing.subscription'))
            ->assertSee('Payment is already pending verification for this billing period.')
            ->assertSee('Not available now');
    }

    public function test_registration_fee_is_added_only_for_first_verified_payment(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00'));
        PaymentSetting::current()->update(['registration_fee' => 5]);
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        $this->actingAs($student)->post(route('student.billing.payments.store'), [
            'subscription_plan_id' => $plan->id,
            'slip' => UploadedFile::fake()->image('first.jpg'),
        ]);

        $firstPayment = SubscriptionPayment::query()->latest()->firstOrFail();
        $this->assertSame(12.33, (float) $firstPayment->amount);
        $this->assertSame(5.0, (float) $firstPayment->pricing_snapshot['registration_fee']);

        $firstPayment->update(['status' => SubscriptionPayment::STATUS_VERIFIED]);

        $this->actingAs($student)->post(route('student.billing.payments.store'), [
            'subscription_plan_id' => $plan->id,
            'slip' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $secondPayment = SubscriptionPayment::query()->latest()->firstOrFail();
        $this->assertSame(0.0, (float) $secondPayment->pricing_snapshot['registration_fee']);
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
