<?php

declare(strict_types=1);

namespace CoreX\Search\Exceptions;

use RuntimeException;

final class InvalidSearchConfiguration extends RuntimeException
{
    public static function missingDriver(): self
    {
        return new self(
            'corex-search.driver must be a non-empty string. Set it to "null", "postgres", or register an adapter driver such as "meilisearch".',
        );
    }

    public static function weakCursorSecret(): self
    {
        return new self(
            'corex-search.cursor_secret must be a string of at least 32 characters when corex-search.driver is "postgres".',
        );
    }

    public static function missingSchemaBinding(): self
    {
        return new self(
            'SearchSchema must be bound in the container when corex-search.driver is "postgres".',
        );
    }

    public static function missingDatabase(): self
    {
        return new self(
            'Database connection must be bound in the container when corex-search.driver is "postgres".',
        );
    }
}
