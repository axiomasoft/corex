<?php

declare(strict_types=1);

namespace CoreX\Search\Meilisearch\Exceptions;

use RuntimeException;

final class InvalidMeilisearchConfiguration extends RuntimeException
{
    public static function invalidHost(): self
    {
        return new self(
            'corex-search-meilisearch.host must be a valid URL string when corex-search.driver is "meilisearch".',
        );
    }

    public static function invalidIndexUid(): self
    {
        return new self(
            'corex-search-meilisearch.index_uid must be a non-empty string when corex-search.driver is "meilisearch".',
        );
    }

    public static function invalidKey(): self
    {
        return new self(
            'corex-search-meilisearch.key must be a non-empty string when corex-search.driver is "meilisearch".',
        );
    }

    public static function missingSdk(): self
    {
        return new self(
            'meilisearch/meilisearch-php is not installed. Require corex/search-meilisearch or run composer require meilisearch/meilisearch-php:1.16.1.',
        );
    }
}
