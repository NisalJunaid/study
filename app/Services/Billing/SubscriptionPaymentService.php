<?php

namespace App\Services\Billing;

use App\Models\PlanDiscount;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentService
{
    public function __construct(private readonly BillingCycleService $billingCycleService)
    {
    }

    public function submitPayment(User $user, SubscriptionPlan $plan, UploadedFile $slip, ?string $discountCode = null): SubscriptionPayment
    {
        return DB::transaction(function () use ($user, $plan, $slip, $discountCode): SubscriptionPayment {
            $discount = $this->resolveDiscount($plan, $discountCode);
            $amount = $this->calculateAmount($plan, $discount);
            $subscription = $user->subscriptions()->latest()->first();

            if (! $subscription) {
                $subscription = $user->subscriptions()->create([
                    'subscription_plan_id' => $plan->id,
                    'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
                    'billing_status' => UserSubscription::BILLING_INACTIVE,
                    'started_at' => now(),
                ]);
            } else {
                $subscription->update([
                    'subscription_plan_id' => $plan->id,
                    'status' => UserSubscription::STATUS_PENDING_VERIFICATION,
                    'billing_status' => UserSubscription::BILLING_INACTIVE,
                    'suspended_reason' => null,
                ]);
            }

            $storedPath = $slip->store('billing/slips');

            return SubscriptionPayment::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'user_subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => $plan->currency,
                'discount_id' => $discount?->id,
                'discount_snapshot' => $discount ? [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'code' => $discount->code,
                    'type' => $discount->type,
                    'amount' => (float) $discount->amount,
                ] : null,
                'status' => SubscriptionPayment::STATUS_PENDING,
                'payment_method' => 'bank_transfer',
                'slip_path' => $storedPath,
                'slip_original_name' => $slip->getClientOriginalName(),
                'submitted_at' => now(),
                'temporary_access_expires_at' => now()->addHours(24),
                'temporary_quiz_limit' => 6,
            ]);
        });
    }

    public function verifyPayment(SubscriptionPayment $payment, User $admin): void
    {
        DB::transaction(function () use ($payment, $admin): void {
            $payment->refresh();

            $verifiedAt = now();

            $payment->update([
                'status' => SubscriptionPayment::STATUS_VERIFIED,
                'verified_at' => $verifiedAt,
                'verified_by' => $admin->id,
                'rejected_at' => null,
                'rejection_reason' => null,
                'temporary_access_expires_at' => null,
            ]);

            $plan = $payment->plan;
            $subscription = $payment->subscription ?? $payment->user->subscriptions()->latest()->first();
            $dates = $this->billingCycleService->computeExpiryForPlan($plan->type, $verifiedAt);

            $subscription?->update([
                'subscription_plan_id' => $plan->id,
                'status' => UserSubscription::STATUS_ACTIVE,
                'billing_status' => UserSubscription::BILLING_ACTIVE,
                'started_at' => $subscription->started_at ?? $verifiedAt,
                'activated_at' => $verifiedAt,
                'verified_at' => $verifiedAt,
                'verified_by' => $admin->id,
                'expires_at' => $dates['expires_at'],
                'grace_ends_at' => $dates['grace_ends_at'],
                'suspended_at' => null,
                'suspended_reason' => null,
            ]);
        });
    }

    public function rejectPayment(SubscriptionPayment $payment, User $admin, string $reason): void
    {
        DB::transaction(function () use ($payment, $admin, $reason): void {
            $payment->update([
                'status' => SubscriptionPayment::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $admin->id,
                'rejection_reason' => $reason,
                'temporary_access_expires_at' => null,
            ]);

            $subscription = $payment->subscription ?? $payment->user->subscriptions()->latest()->first();

            $subscription?->update([
                'status' => UserSubscription::STATUS_REJECTED,
                'billing_status' => UserSubscription::BILLING_SUSPENDED,
                'suspended_at' => now(),
                'suspended_reason' => 'Payment proof was rejected: '.$reason,
            ]);
        });
    }

    private function resolveDiscount(SubscriptionPlan $plan, ?string $discountCode): ?PlanDiscount
    {
        $query = $plan->discounts()->currentlyActive();

        if ($discountCode) {
            return $query->whereRaw('lower(code) = ?', [strtolower($discountCode)])->first();
        }

        return $query->orderByDesc('amount')->first();
    }

    private function calculateAmount(SubscriptionPlan $plan, ?PlanDiscount $discount): float
    {
        $base = (float) $plan->price;

        if (! $discount) {
            return $base;
        }

        if ($discount->type === PlanDiscount::TYPE_PERCENTAGE) {
            return max(0, round($base - ($base * ((float) $discount->amount / 100)), 2));
        }

        return max(0, round($base - (float) $discount->amount, 2));
    }
}
