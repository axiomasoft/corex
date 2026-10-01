<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum SearchMeilisearchErrorCode: string implements ErrorCode
{
    case InvalidMeilisearchConfiguration = 'search-meilisearch.invalid_meilisearch_configuration';

    public function value(): string
    {
        return $this->value;
    }
}
