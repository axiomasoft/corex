<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\SearchMeilisearch\Errors\Enums\SearchMeilisearchErrorCode;

#[Api(since: '1.0')]
final class InvalidMeilisearchConfiguration extends CodedException
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
            'meilisearch/meilisearch-php is not installed. Require axiomasoft/corex-search-meilisearch or run composer require meilisearch/meilisearch-php:1.16.1.',
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(SearchMeilisearchErrorCode::InvalidMeilisearchConfiguration, ErrorCategory::Internal, retryable: false);
    }
}
