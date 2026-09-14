<?php

declare(strict_types=1);

namespace CoreX\Api;

use RuntimeException;

/** Explicit public error. Never pass database or infrastructure exception messages here. */
final class ApiError extends RuntimeException
{
    /** @param  array<string, mixed>  $details */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
