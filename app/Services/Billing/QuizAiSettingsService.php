<?php

namespace App\Services\Billing;

use App\Models\PaymentSetting;

class QuizAiSettingsService
{
    public function mixedQuizAiWeightPercentage(): int
    {
        return max(0, min(100, (int) PaymentSetting::current()->mixed_quiz_ai_weight_percentage));
    }

    public function dailyAiCreditsTotal(): int
    {
        return max(0, (int) PaymentSetting::current()->daily_ai_credits);
    }
}

