<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Exceptions;

use RuntimeException;

final class InvalidAttributeTables extends RuntimeException
{
    public static function invalidIdentifier(string $property, string $table): self
    {
        return new self("Attribute table [{$property}] must be a lowercase PostgreSQL identifier; [{$table}] is invalid.");
    }

    public static function duplicateNames(): self
    {
        return new self('Attribute table names must be unique across the configured table map.');
    }
}
