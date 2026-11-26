<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Exceptions;

use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

/**
 * The current user has no default-workspace membership (B-11 §5.4, D132).
 * Extends {@see RuntimeException} so the existing test
 * (`WorkspaceResolverTest::toThrow(RuntimeException::class)`) stays green —
 * a distinct subclass lets callers (`Http\Middleware\ResolveWorkspace`)
 * translate this specific access-denial into 403 instead of the 500 a bare
 * `RuntimeException` would render.
 */
#[Api(since: '1.0')]
final class NoDefaultWorkspaceException extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::NoDefaultWorkspaceException, category: ErrorCategory::NotFound, retryable: false);
    }
}
