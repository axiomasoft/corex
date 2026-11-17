<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ScopeCapabilityMismatch;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Enums\Entities\Capability;

/**
 * Normalizes a compiled Scope against the entity's declared capabilities
 * (B-11 §4 п.3, D114) before it reaches {@see ScopePredicate}: own/dept/
 * dept_tree require Capability::Ownable, workspace requires
 * Capability::WorkspaceScoped. Letter-only compliance ("no capability =>
 * account") silently widens access when the physical column DOES exist and
 * the entity's declaration is simply missing — that case fails closed with
 * {@see ScopeCapabilityMismatch} instead (declaration drift is a bug in the
 * entity's own registration, not a runtime access decision). Only degrades
 * to `account` when the column is genuinely absent — there is nothing to
 * narrow by.
 *
 * Memoized per (entity_key, scope) for the life of the request so
 * `Schema::hasColumn()` does not hit the schema on every call.
 *
 * @internal spec: B-11 §4 п.3, P2.9 D114
 */
final class ScopeAvailability
{
    /** @var array<string, Scope> */
    private array $memo = [];

    public function __construct(
        private readonly EntityRegistry $entities,
        private readonly ConnectionResolverInterface $connections,
    ) {}

    public function normalize(string $entityKey, Scope $scope): Scope
    {
        $requirement = $this->requirementFor($scope);

        if ($requirement === null) {
            return $scope;
        }

        $memoKey = "{$entityKey}|{$scope->value}";

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        return $this->memo[$memoKey] = $this->resolve(entityKey: $entityKey, scope: $scope, requirement: $requirement);
    }

    /** @return array{capability: Capability, column: string}|null */
    private function requirementFor(Scope $scope): ?array
    {
        return match ($scope) {
            Scope::Own, Scope::Dept, Scope::DeptTree => ['capability' => Capability::Ownable, 'column' => 'owner_id'],
            Scope::Workspace => ['capability' => Capability::WorkspaceScoped, 'column' => 'workspace_id'],
            default => null,
        };
    }

    /** @param array{capability: Capability, column: string} $requirement */
    private function resolve(string $entityKey, Scope $scope, array $requirement): Scope
    {
        // Unknown entity_key: not "widen to account" — the entity is not
        // registered at all, so there is no capability to check. Mirrors
        // canOnRecord()'s own fail-closed unresolved-entity handling.
        if (! $this->entities->has($entityKey)) {
            return Scope::None;
        }

        $entity = $this->entities->get($entityKey);

        if (in_array($requirement['capability'], $entity->capabilities, true)) {
            return $scope;
        }

        if (! $this->schema()->hasColumn($entity->table, $requirement['column'])) {
            return Scope::Account;
        }

        throw ScopeCapabilityMismatch::forScope(entityKey: $entityKey, scope: $scope->value, missingCapability: $requirement['capability']->value, column: $requirement['column']);
    }

    private function schema(): SchemaBuilder
    {
        /** @var Connection */
        $connection = $this->connections->connection();

        return $connection->getSchemaBuilder();
    }
}
