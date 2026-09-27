<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Enums\IdStrategy;
use CoreX\Support\Config;
use Illuminate\Validation\ValidationException;

final class CrmIdentifier
{
    public static function fromPath(string $value): string|int
    {
        return self::from($value);
    }

    public static function fromInput(mixed $value): string|int
    {
        return self::from($value);
    }

    private static function from(mixed $value): string|int
    {
        if (Config::idStrategy() === IdStrategy::Int) {
            if (is_int($value) && $value > 0) {
                return $value;
            }

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
                return (int) $value;
            }

            throw ValidationException::withMessages(['id' => 'CRM identifier must be a positive integer.']);
        }

        if (is_string($value)) {
            return $value;
        }

        throw ValidationException::withMessages(['id' => 'CRM identifier must be a string.']);
    }
}
