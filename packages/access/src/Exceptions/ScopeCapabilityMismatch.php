<?php

declare(strict_types=1);

namespace CoreX\Auth\Exceptions;

use RuntimeException;

/**
 * An entity's matrix row names a scope (own/dept/dept_tree/workspace) whose
 * physical column exists but whose capability was never declared on the
 * entity's `EntityDefinition` (B-11 §4 п.3, D114). Letter-only compliance
 * would silently widen the scope to `account` here — a forgotten `ownable`
 * declaration turning "sees own records" into "sees everything" — so this
 * is a fail-closed exception instead: declaration drift is a bug in the
 * entity's own registration, fixed by declaring the capability, never
 * papered over by a wider grant.
 *
 * @internal spec: B-11 §4 п.3, P2.9 D114
 */
final class ScopeCapabilityMismatch extends RuntimeException
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
}
