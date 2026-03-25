<?php

namespace App\Support\Overlay;

class OverlayPayloadFactory
{
    public const DEFAULT_REDIRECT_DELAY_MS = 2800;

    public static function info(string $title, string $message, array $overrides = []): array
    {
        return self::build($title, $message, 'info', $overrides);
    }

    public static function warning(string $title, string $message, array $overrides = []): array
    {
        return self::build($title, $message, 'warning', $overrides);
    }

    public static function success(string $title, string $message, array $overrides = []): array
    {
        return self::build($title, $message, 'success', $overrides);
    }

    public static function redirect(
        string $title,
        string $message,
        string $redirectUrl,
        string $variant = 'warning',
        array $overrides = []
    ): array {
        return self::build($title, $message, $variant, array_merge([
            'primary_url' => $redirectUrl,
            'redirect_url' => $redirectUrl,
            'redirect_delay_ms' => self::DEFAULT_REDIRECT_DELAY_MS,
            'primary_label' => 'Continue',
            'dismissible' => false,
            'blocking' => true,
        ], $overrides));
    }

    public static function confirm(string $title, string $message, array $overrides = []): array
    {
        return self::build($title, $message, 'confirm', array_merge([
            'primary_label' => 'Confirm',
            'secondary_label' => 'Cancel',
            'dismissible' => true,
            'blocking' => false,
        ], $overrides));
    }

    public static function fromFlash(?array $overlay, mixed $success, mixed $error): ?array
    {
        if (is_array($overlay)) {
            return self::renderableOrNull($overlay);
        }

        if (filled($success)) {
            return self::renderableOrNull(self::success('Success', (string) $success));
        }

        if (filled($error)) {
            return self::renderableOrNull(self::warning('Action needed', (string) $error));
        }

        return null;
    }

    public static function renderableOrNull(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $normalized = self::normalize($payload);

        return self::isRenderable($normalized) ? $normalized : null;
    }

    public static function normalize(array $payload): array
    {
        $redirectUrl = self::clean(data_get($payload, 'redirect_url'));
        $primaryUrl = self::clean(data_get($payload, 'primary_url'));

        $normalized = [
            'title' => self::clean(data_get($payload, 'title')),
            'message' => self::clean(data_get($payload, 'message')),
            'variant' => self::clean(data_get($payload, 'variant')) ?: 'info',
            'primary_label' => self::clean(data_get($payload, 'primary_label')),
            'primary_url' => $primaryUrl,
            'secondary_label' => self::clean(data_get($payload, 'secondary_label')),
            'secondary_url' => self::clean(data_get($payload, 'secondary_url')),
            'redirect_url' => $redirectUrl ?: $primaryUrl,
            'redirect_delay_ms' => self::normalizeDelay(data_get($payload, 'redirect_delay_ms', data_get($payload, 'auto_redirect_delay_ms'))),
            'dismissible' => data_get($payload, 'dismissible') !== false,
            'blocking' => data_get($payload, 'blocking') === true,
        ];

        if (! filled($normalized['primary_label']) && filled($normalized['redirect_url'])) {
            $normalized['primary_label'] = 'Continue';
        }

        if (! filled($normalized['primary_label']) && (filled($normalized['title']) || filled($normalized['message']))) {
            $normalized['primary_label'] = 'Okay';
        }

        return $normalized;
    }

    public static function isRenderable(array $payload): bool
    {
        if (! self::hasMeaningfulPurpose($payload)) {
            return false;
        }

        if (($payload['blocking'] ?? false) === true && ! self::hasBlockingActionPath($payload)) {
            return false;
        }

        if (($payload['blocking'] ?? false) === true && ! self::hasHeadline($payload)) {
            return false;
        }

        return true;
    }

    public static function hasMeaningfulPurpose(array $payload): bool
    {
        if (($payload['blocking'] ?? false) === true) {
            return self::hasHeadline($payload) && self::hasBlockingActionPath($payload);
        }

        return filled($payload['title'] ?? null)
            || filled($payload['message'] ?? null)
            || filled($payload['redirect_url'] ?? null)
            || filled($payload['primary_url'] ?? null)
            || (($payload['variant'] ?? null) === 'confirm' && filled($payload['primary_label'] ?? null));
    }

    public static function hasBlockingActionPath(array $payload): bool
    {
        return filled($payload['primary_url'] ?? null)
            || filled($payload['redirect_url'] ?? null)
            || filled($payload['secondary_url'] ?? null);
    }

    public static function hasHeadline(array $payload): bool
    {
        return filled($payload['title'] ?? null)
            || filled($payload['message'] ?? null);
    }

    private static function build(string $title, string $message, string $variant, array $overrides): array
    {
        return self::normalize(array_merge([
            'title' => $title,
            'message' => $message,
            'variant' => $variant,
            'primary_label' => 'Okay',
            'dismissible' => true,
            'blocking' => false,
        ], $overrides));
    }

    private static function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private static function normalizeDelay(mixed $value): int
    {
        $delay = (int) $value;

        return $delay > 0 ? $delay : self::DEFAULT_REDIRECT_DELAY_MS;
    }
}
