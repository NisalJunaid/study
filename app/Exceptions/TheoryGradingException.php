<?php

namespace App\Exceptions;

use RuntimeException;

class TheoryGradingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retriable = false,
        public readonly ?int $providerStatus = null,
    ) {
        parent::__construct($message);
    }
}

