<?php

namespace App\Services\Billing;

use App\Models\Question;
use App\Models\User;

class AiCreditQuotaService
{
    public function summaryForUser(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                'total' => 0,
                'used' => 0,
                'available' => 0,
            ];
        }

        $total = app(QuizAiSettingsService::class)->dailyAiCreditsTotal();
        $used = $this->usedCreditsToday($user);

        return [
            'total' => $total,
            'used' => $used,
            'available' => max(0, $total - $used),
        ];
    }

    private function usedCreditsToday(User $user): int
    {
        $today = now()->toDateString();

        $quizzes = $user->quizzes()
            ->whereNotNull('submitted_at')
            ->whereDate('submitted_at', $today)
            ->with(['quizQuestions:id,quiz_id,question_snapshot'])
            ->get(['id']);

        return $quizzes
            ->flatMap->quizQuestions
            ->filter(fn ($quizQuestion) => in_array(($quizQuestion->question_snapshot['type'] ?? null), Question::theoryLikeTypes(), true))
            ->count();
    }
}

