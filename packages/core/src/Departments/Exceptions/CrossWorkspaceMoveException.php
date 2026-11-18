<?php

declare(strict_types=1);

namespace CoreX\Departments\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

/**
 * Move-guard, symmetric to the descendant-side filter: the new parent must
 * live in the SAME workspace as the moving department. ltree paths are
 * globally unique today, so a cross-workspace parent passes the cycle check
 * and the FK layer silently — reparenting a row into another tenant's
 * subtree is a cross-tenant integrity breach, not a legal move.
 */
#[Api(since: '1.0')]
final class CrossWorkspaceMoveException extends CodedException
{
    public function __construct(string $departmentId, string $newParentId)
    {
        parent::__construct(sprintf(
            'Cannot move department %s under %s — the new parent belongs to a different workspace (B-10 §5.7 tenant isolation).',
            $departmentId,
            $newParentId,
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::CrossWorkspaceMoveException, category: ErrorCategory::Conflict, retryable: false);
    }
}
