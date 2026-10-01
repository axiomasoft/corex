<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Api(since: '1.0')]
final class HasEnabledDependentsException extends CodedException
{
    /** @param  list<string>  $dependents */
    public function __construct(string $module, array $dependents)
    {
        parent::__construct(sprintf(
            'Module [%s] has enabled dependents [%s]. Pass cascade: true to disable them first.',
            $module,
            implode(', ', $dependents),
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::HasEnabledDependentsException, ErrorCategory::Conflict, retryable: false);
    }
}
