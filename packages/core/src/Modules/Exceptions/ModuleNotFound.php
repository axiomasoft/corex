<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class ModuleNotFound extends CodedException
{
    public function __construct(string $composerName)
    {
        parent::__construct(sprintf('Module [%s] is not in the compiled registry.', $composerName));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::ModuleNotFound, category: ErrorCategory::NotFound, retryable: false);
    }
}
