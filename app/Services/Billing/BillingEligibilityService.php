<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BillingEligibilityService
{
    public function __construct(private readonly BillingPricingService $pricingService)
    {
    }

    public function describePlanState(User $user, SubscriptionPlan $plan, ?Carbon $at = null): array
    {
        $now = ($at ?? now())->copy();
        $subscription = $user->subscriptions()->with('plan')->latest()->first();
        $isMonthlyActive = $subscription
            && $subscription->isActive()
            && $subscription->plan?->type === SubscriptionPlan::TYPE_MONTHLY
            && $plan->type === SubscriptionPlan::TYPE_MONTHLY;

        $period = $this->resolvePeriod($plan, $subscription, $now);
        $pending = $this->pendingPaymentForPeriod($user, $plan, $period['start'], $period['end']);
        $discount = $plan->discounts()->currentlyActive()->orderByDesc('amount')->first();
        $pricing = $this->pricingService->buildBreakdown($user, $plan, $discount, $period['start'], $period['end'], $now);

        if ($pending) {
            return [
                'can_select' => false,
                'state' => 'pending_verification',
                'message' => 'Payment is already pending verification for this billing period.',
                'subscription' => $subscription,
                'pending_payment' => $pending,
                'period' => $period,
                'pricing' => $pricing,
            ];
        }

        if ($isMonthlyActive) {
            $window = $this->monthlyRenewalWindow($subscription, $now);

            if (! $window['is_open']) {
                return [
                    'can_select' => false,
                    'state' => 'active_not_yet_renewable',
                    'message' => 'Your monthly plan is active. Renewal opens on '.$window['opens_at']->format('M d, Y').'.',
                    'subscription' => $subscription,
                    'pending_payment' => null,
                    'period' => $period,
                    'pricing' => $pricing,
                    'window' => $window,
                ];
            }

            return [
                'can_select' => true,
                'state' => 'renewal_due',
                'message' => 'Monthly renewal is now open for next month.',
                'subscription' => $subscription,
                'pending_payment' => null,
                'period' => $period,
                'pricing' => $pricing,
                'window' => $window,
            ];
        }

        if ($subscription && $subscription->isSuspended()) {
            return [
                'can_select' => true,
                'state' => 'suspended_recovery',
                'message' => 'Your account is suspended. Complete payment to restore access.',
                'subscription' => $subscription,
                'pending_payment' => null,
                'period' => $period,
                'pricing' => $pricing,
            ];
        }

        return [
            'can_select' => true,
            'state' => $plan->type === SubscriptionPlan::TYPE_ANNUAL ? 'annual_available' : 'available',
            'message' => 'This plan is available for payment.',
            'subscription' => $subscription,
            'pending_payment' => null,
            'period' => $period,
            'pricing' => $pricing,
        ];
    }

    public function ensurePlanCanBePurchased(User $user, SubscriptionPlan $plan, ?Carbon $at = null): array
    {
        $state = $this->describePlanState($user, $plan, $at);
        if (! $state['can_select']) {
            throw ValidationException::withMessages([
                'subscription_plan_id' => $state['message'],
            ]);
        }

        return $state;
    }

    private function resolvePeriod(SubscriptionPlan $plan, ?UserSubscription $subscription, Carbon $now): array
    {
        if ($plan->type !== SubscriptionPlan::TYPE_MONTHLY) {
            $start = $now->copy()->startOfDay();
            $end = $now->copy()->addYear()->subDay()->endOfDay();

            return ['start' => $start, 'end' => $end];
        }

        $isMonthlyActive = $subscription
            && $subscription->isActive()
            && $subscription->plan?->type === SubscriptionPlan::TYPE_MONTHLY;

        if ($isMonthlyActive) {
            $window = $this->monthlyRenewalWindow($subscription, $now);
            if ($window['is_open']) {
                return ['start' => $window['next_period_start'], 'end' => $window['next_period_end']];
            }
        }

        return [
            'start' => $now->copy()->startOfMonth()->startOfDay(),
            'end' => $now->copy()->endOfMonth()->endOfDay(),
        ];
    }

    private function pendingPaymentForPeriod(User $user, SubscriptionPlan $plan, Carbon $periodStart, Carbon $periodEnd): ?SubscriptionPayment
    {
        return $user->payments()
            ->where('subscription_plan_id', $plan->id)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->whereDate('billing_period_start', $periodStart->toDateString())
            ->whereDate('billing_period_end', $periodEnd->toDateString())
            ->latest('submitted_at')
            ->first();
    }

    private function monthlyRenewalWindow(UserSubscription $subscription, Carbon $now): array
    {
        $cycleMonth = $subscription->expires_at
            ? $subscription->expires_at->copy()->subMonth()->startOfMonth()
            : $now->copy()->startOfMonth();

        $opensAt = $cycleMonth->copy()->day(24)->startOfDay();
        $closesAt = $cycleMonth->copy()->addMonth()->day(3)->endOfDay();

        return [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_open' => $now->between($opensAt, $closesAt, true),
            'next_period_start' => $cycleMonth->copy()->addMonth()->startOfMonth(),
            'next_period_end' => $cycleMonth->copy()->addMonth()->endOfMonth(),
        ];
    }
}
