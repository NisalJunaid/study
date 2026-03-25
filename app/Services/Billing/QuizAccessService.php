<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;

class QuizAccessService
{
    public const ACCESS_ACTIVE_SUBSCRIPTION = 'active_subscription';
    public const ACCESS_FREE_TRIAL = 'free_trial';
    public const ACCESS_TEMPORARY_PENDING_PAYMENT = 'temporary_pending_payment';
    public const ACCESS_BLOCKED = 'blocked';

    public function evaluate(User $user, int $questionCount): array
    {
        $subscription = $user->subscriptions()->latest()->first();

        if ($subscription && $subscription->isSuspended()) {
            return [
                'allowed' => false,
                'access_type' => self::ACCESS_BLOCKED,
                'message' => $subscription->suspended_reason ?: 'Your subscription is suspended. Please submit and verify payment to continue.',
            ];
        }

        if ($subscription && $subscription->isActive()) {
            return [
                'allowed' => true,
                'access_type' => self::ACCESS_ACTIVE_SUBSCRIPTION,
                'message' => 'Subscription active. You can continue practicing.',
                'subscription' => $subscription,
            ];
        }

        if ($user->hasTrialRemaining()) {
            if ($questionCount > 10) {
                return [
                    'allowed' => false,
                    'access_type' => self::ACCESS_FREE_TRIAL,
                    'message' => 'Your free trial supports one quiz with up to 10 questions.',
                ];
            }

            return [
                'allowed' => true,
                'access_type' => self::ACCESS_FREE_TRIAL,
                'message' => 'You are using your free trial quiz.',
            ];
        }

        $payment = $user->payments()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->latest('submitted_at')
            ->first();

        if ($payment && $payment->temporaryAccessStillValid()) {
            $usage = $user->dailyQuizUsages()
                ->where('subscription_payment_id', $payment->id)
                ->whereDate('usage_date', now()->toDateString())
                ->first();

            $usedCount = (int) ($usage?->quiz_count ?? 0);
            $remaining = max(0, $payment->temporary_quiz_limit - $usedCount);

            if ($remaining <= 0) {
                return [
                    'allowed' => false,
                    'access_type' => self::ACCESS_TEMPORARY_PENDING_PAYMENT,
                    'message' => 'Daily temporary access limit reached (6 quizzes). Please try again tomorrow or wait for verification.',
                    'payment' => $payment,
                ];
            }

            return [
                'allowed' => true,
                'access_type' => self::ACCESS_TEMPORARY_PENDING_PAYMENT,
                'message' => "Payment submitted and pending verification. Temporary access remaining today: {$remaining} quiz(es).",
                'payment' => $payment,
                'remaining' => $remaining,
            ];
        }

        if ($subscription && $subscription->status === UserSubscription::STATUS_REJECTED) {
            return [
                'allowed' => false,
                'access_type' => self::ACCESS_BLOCKED,
                'message' => 'Your last payment was rejected. Please upload a new payment slip.',
            ];
        }

        return [
            'allowed' => false,
            'access_type' => self::ACCESS_BLOCKED,
            'message' => 'Your free trial has ended. Choose a plan and upload payment proof to continue.',
        ];
    }

    public function registerQuizUsage(User $user, array $access): void
    {
        if (($access['access_type'] ?? null) !== self::ACCESS_TEMPORARY_PENDING_PAYMENT || empty($access['payment'])) {
            return;
        }

        $payment = $access['payment'];

        $usage = $user->dailyQuizUsages()->firstOrCreate([
            'subscription_payment_id' => $payment->id,
            'usage_date' => now()->toDateString(),
        ], [
            'quiz_count' => 0,
        ]);

        $usage->increment('quiz_count');
    }
}
