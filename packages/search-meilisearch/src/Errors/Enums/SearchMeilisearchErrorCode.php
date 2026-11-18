<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum SearchMeilisearchErrorCode: string implements ErrorCode
{
    case InvalidMeilisearchConfiguration = 'search-meilisearch.invalid_meilisearch_configuration';

    public function value(): string
    {
        return $this->value;
    }
}
