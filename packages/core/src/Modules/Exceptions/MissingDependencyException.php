<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class MissingDependencyException extends CodedException
{
    public function __construct(string $module, string $missingDependency)
    {
        parent::__construct(sprintf(
            'Module [%s] requires [%s], which is not enabled. Pass cascade: true to auto-enable it.',
            $module,
            $missingDependency,
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::MissingDependencyException, category: ErrorCategory::Conflict, retryable: false);
    }
}
