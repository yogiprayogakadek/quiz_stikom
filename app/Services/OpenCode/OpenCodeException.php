<?php

namespace App\Services\OpenCode;

use RuntimeException;
use Throwable;

class OpenCodeException extends RuntimeException
{
    public function __construct(
        string $message,
        private ?int $status = null,
        private bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): ?int
    {
        return $this->status;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
