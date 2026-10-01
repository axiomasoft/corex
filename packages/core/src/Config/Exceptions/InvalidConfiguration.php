<?php

declare(strict_types=1);

namespace CoreX\Config\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use InvalidArgumentException;

#[Api(since: '1.0')]
final class InvalidConfiguration extends InvalidArgumentException implements DescribesError
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(
            'Invalid configuration ('.count($violations).'): '.implode('; ', $violations),
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::InvalidConfiguration, ErrorCategory::Internal, retryable: false);
    }
}
