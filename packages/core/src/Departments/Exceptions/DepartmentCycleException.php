<?php

declare(strict_types=1);

namespace CoreX\Departments\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

/**
 * Move-guard: the new parent must not be inside the moving department's own
 * subtree (`new_parent.path <@ moving.path`).
 */
#[Api(since: '1.0')]
final class DepartmentCycleException extends CodedException
{
    public function __construct(string $departmentId, string $newParentId)
    {
        parent::__construct(sprintf(
            'Cannot move department %s under %s — the new parent is inside the moving subtree (B-10 §5.7).',
            $departmentId,
            $newParentId,
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::DepartmentCycleException, category: ErrorCategory::Conflict, retryable: false);
    }
}
