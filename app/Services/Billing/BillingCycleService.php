<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPlan;
use Carbon\CarbonInterface;

class BillingCycleService
{
    public function nextMonthlyExpiry(CarbonInterface $verifiedAt): CarbonInterface
    {
        return $verifiedAt->copy()->startOfMonth()->addMonth()->day(3)->endOfDay();
    }

    public function annualExpiry(CarbonInterface $verifiedAt): CarbonInterface
    {
        return $verifiedAt->copy()->addYear();
    }

    public function annualGraceEnd(CarbonInterface $expiresAt): CarbonInterface
    {
        return $expiresAt->copy()->addDays(3)->endOfDay();
    }

    public function computeExpiryForPlan(string $planType, CarbonInterface $verifiedAt): array
    {
        if ($planType === SubscriptionPlan::TYPE_ANNUAL) {
            $expiresAt = $this->annualExpiry($verifiedAt);

            return [
                'expires_at' => $expiresAt,
                'grace_ends_at' => $this->annualGraceEnd($expiresAt),
            ];
        }

        return [
            'expires_at' => $this->nextMonthlyExpiry($verifiedAt),
            'grace_ends_at' => null,
        ];
    }
}
