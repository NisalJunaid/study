<?php

namespace App\Support;

use App\Services\Billing\QuizAccessService;
use App\Support\Overlay\OverlayPayloadFactory;

class OverlayMessage
{
    public static function make(string $title, string $message, string $variant = 'info', array $overrides = []): array
    {
        return match ($variant) {
            'success' => OverlayPayloadFactory::success($title, $message, $overrides),
            'warning', 'danger' => OverlayPayloadFactory::warning($title, $message, array_merge(['variant' => $variant], $overrides)),
            'confirm' => OverlayPayloadFactory::confirm($title, $message, $overrides),
            default => OverlayPayloadFactory::info($title, $message, array_merge(['variant' => $variant], $overrides)),
        };
    }

    public static function redirect(
        string $title,
        string $message,
        string $redirectUrl,
        string $variant = 'warning',
        array $overrides = []
    ): array {
        return OverlayPayloadFactory::redirect($title, $message, $redirectUrl, $variant, $overrides);
    }

    public static function billingAccessRequired(array $access): array
    {
        $type = $access['access_type'] ?? QuizAccessService::ACCESS_BLOCKED;
        $message = (string) ($access['message'] ?? 'Billing access required before continuing.');

        return match ($type) {
            QuizAccessService::ACCESS_FREE_TRIAL => self::redirect(
                title: 'Free trial complete',
                message: $message,
                redirectUrl: route('student.billing.subscription'),
                variant: 'warning',
                overrides: [
                    'primary_label' => 'Choose a Plan',
                ],
            ),
            QuizAccessService::ACCESS_TEMPORARY_PENDING_PAYMENT => self::redirect(
                title: 'Payment verification pending',
                message: $message,
                redirectUrl: route('student.billing.subscription'),
                variant: 'warning',
                overrides: [
                    'primary_label' => 'Review Billing Status',
                ],
            ),
            default => self::redirect(
                title: str_contains(strtolower($message), 'rejected') ? 'Payment proof rejected' : 'Billing access required',
                message: $message,
                redirectUrl: route('student.billing.subscription'),
                variant: 'danger',
                overrides: [
                    'primary_label' => str_contains(strtolower($message), 'rejected') ? 'Upload New Proof' : 'Go to Subscription',
                ],
            ),
        };
    }

    public static function suspendedAccount(string $message): array
    {
        return self::redirect(
            title: 'Account access paused',
            message: $message,
            redirectUrl: route('student.billing.subscription'),
            variant: 'danger',
            overrides: [
                'primary_label' => 'Resolve Subscription',
            ],
        );
    }

    public static function paymentSubmitted(): array
    {
        return self::redirect(
            title: 'Payment submitted successfully',
            message: 'Temporary access is now active for up to 6 quizzes today while admin verification is pending.',
            redirectUrl: route('student.quiz.setup'),
            variant: 'success',
            overrides: [
                'primary_label' => 'Start Quiz',
                'blocking' => false,
                'dismissible' => true,
                'redirect_delay_ms' => 3200,
            ],
        );
    }

    public static function renderableOrNull(?array $payload): ?array
    {
        return OverlayPayloadFactory::renderableOrNull($payload);
    }

    public static function fromFlash(?array $overlay, mixed $success, mixed $error): ?array
    {
        return OverlayPayloadFactory::fromFlash($overlay, $success, $error);
    }
}
