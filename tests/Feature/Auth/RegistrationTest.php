<?php

namespace Tests\Feature\Auth;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        SubscriptionPlan::query()->create($this->monthlyPlanData());

        $response = $this->get('/register');

        $response->assertStatus(200)
            ->assertSee('Create Account')
            ->assertSee('Plan Selection')
            ->assertSee('Billing &amp; Payment');
    }

    public function test_new_users_can_register_with_payment_submission(): void
    {
        Storage::fake('local');
        $plan = SubscriptionPlan::query()->create($this->monthlyPlanData());

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'id_document_number' => 'P1234567',
            'nationality' => 'Malaysian',
            'contact_number' => '+60-1000000',
            'id_document' => UploadedFile::fake()->image('id-card.jpg'),
            'subscription_plan_id' => $plan->id,
            'slip' => UploadedFile::fake()->image('slip.jpg'),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.billing.subscription'));

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->id_document_path);
        $this->assertSame(User::ONBOARDING_SUBSCRIBE, $user->onboarding_intent);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
            'billing_status' => UserSubscription::BILLING_INACTIVE,
        ]);

        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);
    }

    private function monthlyPlanData(): array
    {
        return [
            'code' => 'monthly-register',
            'name' => 'Monthly Plan',
            'type' => SubscriptionPlan::TYPE_MONTHLY,
            'price' => 30,
            'currency' => 'USD',
            'billing_cycle_days' => 30,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
