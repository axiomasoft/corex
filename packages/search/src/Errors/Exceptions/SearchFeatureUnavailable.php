<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Exceptions;

use CoreX\Search\Errors\Enums\SearchErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '2.0.0')]
final class SearchFeatureUnavailable extends CodedException
{
    public static function unsupported(): self
    {
        return new self('Unsupported search feature.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: SearchErrorCode::SearchFeatureUnavailable, category: ErrorCategory::Unsupported, retryable: false);
    }
}
