<?php

namespace App\Exceptions;

use RuntimeException;

class AiRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public ?int $status = null,
        public ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }

    /**
     * Gagal koneksi, 429, dan 5xx layak dicoba ulang. Error konfigurasi lain tidak.
     */
    public function transient(): bool
    {
        return $this->status === null || $this->status === 429 || $this->status >= 500;
    }

    public function retryDelay(): int
    {
        return min(max($this->retryAfter ?? 60, 30), 300);
    }
}
