<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class OutboxRefused extends RuntimeException
{
    public static function outsideTransaction(string $contract): self
    {
        return new self(
            "Outbox append for [{$contract}] requires an open transaction: the business change and the "
            .'outbox row must commit together (MSG-008).',
        );
    }

    public static function piiClassExceeded(string $contract, string $declared, string $actual): self
    {
        return new self(
            "Message [{$contract}] declares a maximum personal-data class of [{$declared}] but carries "
            ."[{$actual}] (MSG-007).",
        );
    }
}
