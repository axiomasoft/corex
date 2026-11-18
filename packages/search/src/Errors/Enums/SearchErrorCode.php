<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum SearchErrorCode: string implements ErrorCode
{
    case InvalidSearchConfiguration = 'search.invalid_search_configuration';

    public function value(): string
    {
        return $this->value;
    }
}
