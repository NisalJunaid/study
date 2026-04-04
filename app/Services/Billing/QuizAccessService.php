<?php

namespace App\Services\Billing;

use App\Models\PaymentSetting;
use App\Models\Quiz;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

class QuizAccessService
{
    public const ACCESS_ACTIVE_SUBSCRIPTION = 'active_subscription';
    public const ACCESS_FREE_TRIAL = 'free_trial';
    public const ACCESS_TEMPORARY_PENDING_PAYMENT = 'temporary_pending_payment';
    public const ACCESS_ADMIN_BYPASS = 'admin_bypass';
    public const ACCESS_BLOCKED = 'blocked';

    public const REASON_ALLOWED = 'allowed';
    public const REASON_ACTIVE_SUBSCRIPTION = 'active_subscription';
    public const REASON_FREE_TRIAL = 'free_trial';
    public const REASON_PENDING_VERIFICATION = 'pending_verification';
    public const REASON_DAILY_LIMIT_REACHED = 'daily_limit_reached';
    public const REASON_TEMPORARY_ACCESS_EXPIRED = 'temporary_access_expired';
    public const REASON_PAYMENT_REJECTED = 'payment_rejected';
    public const REASON_ACCOUNT_SUSPENDED = 'account_suspended';
    public const REASON_NO_ACTIVE_ACCESS = 'no_active_access';
    public const REASON_BILLING_CONFIGURATION_INVALID = 'billing_configuration_invalid';
    public const REASON_QUIZ_NOT_FOUND = 'quiz_not_found';
    public const REASON_QUIZ_ALREADY_FINALIZED = 'quiz_already_finalized';

    public function evaluate(User $user, int $questionCount): array
    {
        return $this->canStartQuiz($user, $questionCount);
    }

    public function canStartQuiz(User $user, int $questionCount): array
    {
        if ($user->isAdmin()) {
            return $this->allowedDecision(
                accessType: self::ACCESS_ADMIN_BYPASS,
                reason: self::REASON_ALLOWED,
                message: 'Admin bypass is active.',
                metadata: ['admin_bypass' => true]
            );
        }

        try {
            $subscription = $user->subscriptions()->latest()->first();

            if ($subscription && $subscription->isSuspended()) {
                return $this->blockedDecision(
                    reason: self::REASON_ACCOUNT_SUSPENDED,
                    message: $subscription->suspended_reason ?: 'Your subscription is suspended. Please submit and verify payment to continue.',
                    accessType: self::ACCESS_BLOCKED,
                    metadata: [
                        'subscription_status' => $subscription->status,
                        'billing_status' => $subscription->billing_status,
                    ],
                );
            }

            if ($subscription && $subscription->isActive()) {
                return $this->allowedDecision(
                    accessType: self::ACCESS_ACTIVE_SUBSCRIPTION,
                    reason: self::REASON_ACTIVE_SUBSCRIPTION,
                    message: 'Subscription active. You can continue practicing.',
                    metadata: [
                        'subscription' => $subscription,
                        'subscription_status' => $subscription->status,
                        'billing_status' => $subscription->billing_status,
                    ],
                );
            }

            if ($user->hasTrialRemaining()) {
                if ($questionCount > 10) {
                    return $this->blockedDecision(
                        reason: self::REASON_FREE_TRIAL,
                        message: 'Your free trial supports one quiz with up to 10 questions.',
                        accessType: self::ACCESS_FREE_TRIAL,
                    );
                }

                return $this->allowedDecision(
                    accessType: self::ACCESS_FREE_TRIAL,
                    reason: self::REASON_FREE_TRIAL,
                    message: 'You are using your free trial quiz.',
                );
            }

            $configuration = $this->billingConfigurationState();
            if (! $configuration['is_valid']) {
                $this->logConfigurationAnomaly($user, $configuration);

                return $this->blockedDecision(
                    reason: self::REASON_BILLING_CONFIGURATION_INVALID,
                    message: 'Billing is temporarily unavailable. Please contact support or try again shortly.',
                    accessType: self::ACCESS_BLOCKED,
                    metadata: ['configuration' => $configuration],
                );
            }

            $payment = $this->latestPendingPayment($user);
            if ($payment) {
                $remaining = $this->remainingTemporaryQuota($user, $payment);
                $isTemporaryExpired = ! $payment->temporaryAccessStillValid();

                if ($payment->temporaryAccessStillValid()) {
                    if ($remaining <= 0) {
                        return $this->blockedDecision(
                            reason: self::REASON_DAILY_LIMIT_REACHED,
                            message: 'Daily temporary access limit reached (6 quizzes). Please try again tomorrow or wait for verification.',
                            accessType: self::ACCESS_TEMPORARY_PENDING_PAYMENT,
                            metadata: [
                                'payment' => $payment,
                                'remaining_daily_quota' => 0,
                                'temporary_access_expires_at' => optional($payment->temporary_access_expires_at)?->toIso8601String(),
                                'payment_verification_status' => SubscriptionPayment::STATUS_PENDING,
                            ],
                        );
                    }

                    return $this->allowedDecision(
                        accessType: self::ACCESS_TEMPORARY_PENDING_PAYMENT,
                        reason: self::REASON_PENDING_VERIFICATION,
                        message: "Payment submitted and pending verification. Temporary access remaining today: {$remaining} quiz(es).",
                        metadata: [
                            'payment' => $payment,
                            'remaining_daily_quota' => $remaining,
                            'temporary_access_expires_at' => optional($payment->temporary_access_expires_at)?->toIso8601String(),
                            'payment_verification_status' => SubscriptionPayment::STATUS_PENDING,
                        ],
                    );
                }

                return $this->blockedDecision(
                    reason: self::REASON_TEMPORARY_ACCESS_EXPIRED,
                    message: 'Temporary access has expired while payment verification is pending. Submit a new payment slip to restore access.',
                    accessType: self::ACCESS_BLOCKED,
                    metadata: [
                        'payment' => $payment,
                        'remaining_daily_quota' => max(0, $remaining),
                        'temporary_access_expires_at' => optional($payment->temporary_access_expires_at)?->toIso8601String(),
                        'temporary_access_expired' => $isTemporaryExpired,
                    ],
                );
            }

            if ($subscription && $subscription->status === UserSubscription::STATUS_REJECTED) {
                return $this->blockedDecision(
                    reason: self::REASON_PAYMENT_REJECTED,
                    message: 'Your last payment was rejected. Please upload a new payment slip.',
                    accessType: self::ACCESS_BLOCKED,
                    metadata: ['subscription_status' => $subscription->status],
                );
            }

            return $this->blockedDecision(
                reason: self::REASON_NO_ACTIVE_ACCESS,
                message: 'Your free trial has ended. Choose a plan and upload payment proof to continue.',
                accessType: self::ACCESS_BLOCKED,
            );
        } catch (\Throwable $exception) {
            Log::error('Quiz access evaluation failed closed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->blockedDecision(
                reason: self::REASON_BILLING_CONFIGURATION_INVALID,
                message: 'Billing checks are currently unavailable. Please try again later.',
                accessType: self::ACCESS_BLOCKED,
            );
        }
    }

    public function canResumeQuiz(User $user, ?Quiz $quiz): array
    {
        if (! $quiz || $quiz->user_id !== $user->id) {
            return $this->blockedDecision(self::REASON_QUIZ_NOT_FOUND, 'Quiz was not found for your account.');
        }

        if ($quiz->isSubmittedAttempt()) {
            return $this->blockedDecision(self::REASON_QUIZ_ALREADY_FINALIZED, 'This quiz is already submitted and cannot be resumed.');
        }

        return $this->allowedDecision(
            accessType: (string) ($quiz->billing_access_type ?: self::ACCESS_BLOCKED),
            reason: self::REASON_ALLOWED,
            message: 'You can continue this quiz draft.',
        );
    }

    public function canSubmitQuiz(User $user, ?Quiz $quiz): array
    {
        if (! $quiz || $quiz->user_id !== $user->id) {
            return $this->blockedDecision(self::REASON_QUIZ_NOT_FOUND, 'Quiz was not found for your account.');
        }

        if ($quiz->isSubmittedAttempt()) {
            return $this->blockedDecision(self::REASON_QUIZ_ALREADY_FINALIZED, 'This quiz has already been submitted.');
        }

        return $this->allowedDecision(
            accessType: (string) ($quiz->billing_access_type ?: self::ACCESS_BLOCKED),
            reason: self::REASON_ALLOWED,
            message: 'You can submit this quiz.',
        );
    }

    public function registerSubmittedQuizUsage(User $user, Quiz $quiz): void
    {
        if (! $quiz->isSubmittedAttempt()) {
            return;
        }

        if ($quiz->billing_access_type !== self::ACCESS_TEMPORARY_PENDING_PAYMENT || ! $quiz->subscription_payment_id) {
            return;
        }

        $usage = $user->dailyQuizUsages()->firstOrCreate([
            'subscription_payment_id' => $quiz->subscription_payment_id,
            'usage_date' => now()->toDateString(),
        ], [
            'quiz_count' => 0,
        ]);

        $usage->increment('quiz_count');
    }

    private function latestPendingPayment(User $user): ?SubscriptionPayment
    {
        return $user->payments()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->latest('submitted_at')
            ->first();
    }

    private function remainingTemporaryQuota(User $user, SubscriptionPayment $payment): int
    {
        $usage = $user->dailyQuizUsages()
            ->where('subscription_payment_id', $payment->id)
            ->whereDate('usage_date', now()->toDateString())
            ->first();

        $usedCount = (int) ($usage?->quiz_count ?? 0);

        return max(0, ((int) $payment->temporary_quiz_limit) - $usedCount);
    }

    private function billingConfigurationState(): array
    {
        $setting = PaymentSetting::query()->first();

        return [
            'is_valid' => $setting !== null
                && filled($setting->bank_account_name)
                && filled($setting->bank_account_number)
                && filled($setting->currency)
                && SubscriptionPlan::query()->active()->exists(),
            'has_payment_setting' => $setting !== null,
            'has_active_plan' => SubscriptionPlan::query()->active()->exists(),
        ];
    }

    private function logConfigurationAnomaly(User $user, array $configuration): void
    {
        Log::warning('Quiz access billing configuration is invalid.', [
            'user_id' => $user->id,
            'has_payment_setting' => $configuration['has_payment_setting'] ?? false,
            'has_active_plan' => $configuration['has_active_plan'] ?? false,
        ]);
    }

    private function allowedDecision(string $accessType, string $reason, string $message, array $metadata = []): array
    {
        return array_merge([
            'allowed' => true,
            'access_type' => $accessType,
            'reason' => $reason,
            'message' => $message,
            'remaining_daily_quota' => $metadata['remaining_daily_quota'] ?? null,
            'temporary_access_expires_at' => $metadata['temporary_access_expires_at'] ?? null,
            'temporary_access_expired' => $metadata['temporary_access_expired'] ?? false,
            'subscription_status' => $metadata['subscription_status'] ?? null,
            'billing_status' => $metadata['billing_status'] ?? null,
            'payment_verification_status' => $metadata['payment_verification_status'] ?? null,
        ], $metadata);
    }

    private function blockedDecision(string $reason, string $message, string $accessType = self::ACCESS_BLOCKED, array $metadata = []): array
    {
        return array_merge([
            'allowed' => false,
            'access_type' => $accessType,
            'reason' => $reason,
            'message' => $message,
            'remaining_daily_quota' => $metadata['remaining_daily_quota'] ?? null,
            'temporary_access_expires_at' => $metadata['temporary_access_expires_at'] ?? null,
            'temporary_access_expired' => $metadata['temporary_access_expired'] ?? false,
            'subscription_status' => $metadata['subscription_status'] ?? null,
            'billing_status' => $metadata['billing_status'] ?? null,
            'payment_verification_status' => $metadata['payment_verification_status'] ?? null,
        ], $metadata);
    }
}
