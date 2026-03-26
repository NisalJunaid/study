<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;

class SubscriptionEnforcementService
{
    public function enforce(): array
    {
        $results = [
            'expired_pending_payments' => $this->expirePendingPayments(),
            'monthly_suspensions' => $this->suspendExpiredMonthlySubscriptions(),
            'annual_suspensions' => $this->suspendExpiredAnnualSubscriptions(),
        ];

        return $results;
    }

    private function expirePendingPayments(): int
    {
        $payments = SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->whereNotNull('temporary_access_expires_at')
            ->where('temporary_access_expires_at', '<', now())
            ->get();

        foreach ($payments as $payment) {
            $payment->update([
                'status' => SubscriptionPayment::STATUS_EXPIRED,
            ]);

            $payment->subscription?->update([
                'status' => UserSubscription::STATUS_SUSPENDED,
                'billing_status' => UserSubscription::BILLING_SUSPENDED,
                'suspended_at' => now(),
                'suspended_reason' => 'Temporary access expired after 24 hours pending verification.',
            ]);
        }

        return $payments->count();
    }

    private function suspendExpiredMonthlySubscriptions(): int
    {
        $today = now();

        if ((int) $today->day < 4) {
            return 0;
        }

        $subscriptions = UserSubscription::query()
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->where('billing_status', UserSubscription::BILLING_ACTIVE)
            ->whereHas('plan', fn ($query) => $query->where('type', SubscriptionPlan::TYPE_MONTHLY))
            ->where(function ($query) use ($today): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '<', $today);
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $hasRenewalPending = SubscriptionPayment::query()
                ->where('user_id', $subscription->user_id)
                ->where('subscription_plan_id', $subscription->subscription_plan_id)
                ->where('status', SubscriptionPayment::STATUS_PENDING)
                ->whereDate('billing_period_start', $today->copy()->startOfMonth()->toDateString())
                ->exists();

            if ($hasRenewalPending) {
                continue;
            }

            $subscription->update([
                'status' => UserSubscription::STATUS_SUSPENDED,
                'billing_status' => UserSubscription::BILLING_SUSPENDED,
                'suspended_at' => $today,
                'suspended_reason' => 'Monthly renewal payment was not verified before the 3rd.',
            ]);
        }

        return $subscriptions->count();
    }

    private function suspendExpiredAnnualSubscriptions(): int
    {
        $subscriptions = UserSubscription::query()
            ->where('status', UserSubscription::STATUS_ACTIVE)
            ->where('billing_status', UserSubscription::BILLING_ACTIVE)
            ->whereHas('plan', fn ($query) => $query->where('type', SubscriptionPlan::TYPE_ANNUAL))
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update([
                'status' => UserSubscription::STATUS_SUSPENDED,
                'billing_status' => UserSubscription::BILLING_SUSPENDED,
                'suspended_at' => now(),
                'suspended_reason' => 'Annual subscription expired and grace period ended.',
            ]);
        }

        return $subscriptions->count();
    }
}
