<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Modules\Contracts\ModuleLifecycle;
use Throwable;

/**
 * A module migration or EnableHook failed during {@see ModuleLifecycle::enable()}.
 *
 * enable() is all-or-nothing: the failure rolls the WHOLE lifecycle
 * transaction back — no partially-applied migrations, no `mod_modules` row —
 * and rethrows this so the caller (a control-plane job) learns of it instead
 * of a module silently stuck half-installed. A retry after the fix starts from
 * a clean state.
 */
#[Api(since: '1.0')]
final class ModuleEnableFailedException extends CodedException
{
    public function __construct(string $module, Throwable $previous)
    {
        parent::__construct(
            sprintf('Enabling module [%s] failed and was rolled back: %s', $module, $previous->getMessage()),
            previous: $previous,
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::ModuleEnableFailedException, category: ErrorCategory::Internal, retryable: false);
    }
}
