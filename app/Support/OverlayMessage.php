<?php

namespace App\Support;

class OverlayMessage
{
    public static function make(string $title, string $message, string $variant = 'info', array $overrides = []): array
    {
        return array_merge([
            'title' => $title,
            'message' => $message,
            'variant' => $variant,
            'primary_label' => 'Okay',
            'dismissible' => true,
            'blocking' => false,
        ], $overrides);
    }

    public static function redirect(
        string $title,
        string $message,
        string $redirectUrl,
        string $variant = 'warning',
        array $overrides = []
    ): array {
        return self::make($title, $message, $variant, array_merge([
            'primary_url' => $redirectUrl,
            'redirect_url' => $redirectUrl,
            'redirect_delay_ms' => 2800,
            'primary_label' => 'Continue',
            'dismissible' => false,
            'blocking' => true,
        ], $overrides));
    }
}
