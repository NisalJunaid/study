<?php

namespace App\Services\Billing;

use App\Models\PaymentSetting;
use App\Models\PlanDiscount;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;

class BillingPricingService
{
    public function buildBreakdown(
        User $user,
        SubscriptionPlan $plan,
        ?PlanDiscount $discount,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $now
    ): array {
        $baseAmount = (float) $plan->price;
        $proratedAmount = $plan->type === SubscriptionPlan::TYPE_MONTHLY
            ? $this->proratedMonthlyAmount($baseAmount, $periodStart, $periodEnd, $now)
            : $baseAmount;

        $discountAmount = $this->discountAmount($proratedAmount, $discount);
        $registrationFee = $this->registrationFee($user);
        $planCharge = max(0, round($proratedAmount - $discountAmount, 2));
        $total = round($planCharge + $registrationFee, 2);

        return [
            'currency' => $plan->currency,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'base_plan_amount' => round($baseAmount, 2),
            'prorated_plan_amount' => round($proratedAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'registration_fee' => round($registrationFee, 2),
            'plan_charge' => $planCharge,
            'total_due' => $total,
            'is_prorated' => $plan->type === SubscriptionPlan::TYPE_MONTHLY && round($proratedAmount, 2) < round($baseAmount, 2),
            'is_first_paid_subscription' => $registrationFee > 0,
            'discount_snapshot' => $discount ? [
                'id' => $discount->id,
                'name' => $discount->name,
                'code' => $discount->code,
                'type' => $discount->type,
                'amount' => (float) $discount->amount,
            ] : null,
        ];
    }

    private function proratedMonthlyAmount(float $baseAmount, Carbon $periodStart, Carbon $periodEnd, Carbon $now): float
    {
        if ($periodStart->greaterThan($now->copy()->startOfDay())) {
            return round($baseAmount, 2);
        }

        $remainingDays = max(1, $now->copy()->startOfDay()->diffInDays($periodEnd->copy()->endOfDay()) + 1);
        $dailyRate = $baseAmount / 30;

        return round($dailyRate * $remainingDays, 2);
    }

    private function discountAmount(float $chargeAmount, ?PlanDiscount $discount): float
    {
        if (! $discount) {
            return 0;
        }

        if ($discount->type === PlanDiscount::TYPE_PERCENTAGE) {
            return min($chargeAmount, round($chargeAmount * ((float) $discount->amount / 100), 2));
        }

        return min($chargeAmount, round((float) $discount->amount, 2));
    }

    private function registrationFee(User $user): float
    {
        $hasVerified = $user->payments()->where('status', SubscriptionPayment::STATUS_VERIFIED)->exists();
        if ($hasVerified) {
            return 0;
        }

        return round((float) PaymentSetting::current()->registration_fee, 2);
    }
}
