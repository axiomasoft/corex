<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Exceptions;

use CoreX\SearchMeilisearch\Errors\Enums\SearchMeilisearchErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class InvalidMeilisearchConfiguration extends CodedException
{
    public static function invalidHost(): self
    {
        return new self(
            'corex-search-meilisearch.host must be a valid URL string when corex-search.driver is "meilisearch".',
        );
    }

    public static function invalidKey(): self
    {
        return new self('The Meilisearch credential for the selected index and access mode is empty.');
    }

    public static function missingCredentialProvider(): self
    {
        return new self('A scoped Meilisearch credential provider is required for the Meilisearch driver.');
    }

    public static function missingSdk(): self
    {
        return new self(
            'meilisearch/meilisearch-php is not installed. Require axiomasoft/corex-search-meilisearch or run composer require meilisearch/meilisearch-php:1.16.1.',
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: SearchMeilisearchErrorCode::InvalidMeilisearchConfiguration, category: ErrorCategory::Internal, retryable: false);
    }
}
