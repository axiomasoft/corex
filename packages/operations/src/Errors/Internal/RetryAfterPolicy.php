<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

/** @internal Closed duration names. A retryable flag is not a duration. */
final class RetryAfterPolicy
{
    public const string RATE_LIMIT = 'rate_limit';

    /** @var array<string, int> */
    private const array SECONDS = [
        self::RATE_LIMIT => 60,
    ];

    public static function seconds(?string $name): ?int
    {
        if ($name === null || ! array_key_exists($name, self::SECONDS)) {
            return null;
        }

        return self::SECONDS[$name];
    }
}
