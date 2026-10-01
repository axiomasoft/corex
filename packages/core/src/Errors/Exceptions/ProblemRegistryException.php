<?php

declare(strict_types=1);

namespace CoreX\Errors\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;

#[Api(since: '1.0')]
final class ProblemRegistryException extends CodedException
{
    public function __construct(public readonly CoreErrorCode $errorCode, string $diagnostic)
    {
        parent::__construct($diagnostic);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor($this->errorCode, ErrorCategory::Internal);
    }
}
