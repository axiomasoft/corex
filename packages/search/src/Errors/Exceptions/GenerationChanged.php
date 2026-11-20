<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Exceptions;

use CoreX\Search\Errors\Enums\SearchErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Api(since: '2.0.0')]
final class GenerationChanged extends CodedException
{
    public static function changed(): self
    {
        return new self('Search generation changed.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: SearchErrorCode::GenerationChanged, category: ErrorCategory::Conflict, retryable: false);
    }
}
