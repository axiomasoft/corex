<?php

declare(strict_types=1);

namespace Flex\Crm\ApiShared;

use Illuminate\Validation\ValidationException;

final class CrmIdentifier
{
    public static function fromPath(string $value): string
    {
        return self::from($value);
    }

    public static function fromInput(mixed $value): string
    {
        return self::from($value);
    }

    private static function from(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw ValidationException::withMessages(['id' => 'CRM identifier must be a string.']);
    }
}
