<?php

declare(strict_types=1);

namespace CoreX\Access\Exceptions;

use CoreX\Access\Errors\Enums\AccessErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

/**
 * An entity's matrix row names a scope (own/dept/dept_tree/workspace) whose
 * physical column exists but whose capability was never declared on the
 * entity's `EntityDefinition`. Letter-only compliance
 * would silently widen the scope to `account` here — a forgotten `ownable`
 * declaration turning "sees own records" into "sees everything" — so this
 * is a fail-closed exception instead: declaration drift is a bug in the
 * entity's own registration, fixed by declaring the capability, never
 * papered over by a wider grant.
 */
#[Api(since: '1.0')]
final class ScopeCapabilityMismatch extends CodedException
{
    public static function forScope(string $entityKey, string $scope, string $missingCapability, string $column): self
    {
        return new self(sprintf(
            'Entity [%s] has a matrix row with scope [%s], which requires capability [%s] because column [%s] exists on its table — but the entity does not declare that capability. Declare it on the EntityDefinition rather than letting the scope silently widen to account.',
            $entityKey,
            $scope,
            $missingCapability,
            $column,
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: AccessErrorCode::ScopeCapabilityMismatch, category: ErrorCategory::Unsupported, retryable: false);
    }
}
