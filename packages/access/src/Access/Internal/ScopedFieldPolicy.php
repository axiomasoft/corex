<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Contracts\Scopes\ProvidesAccessScope;
use Closure;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ExactVisibilityUnavailable;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\Principal;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUseSnapshot;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;

#[Internal]
final readonly class ScopedFieldPolicy implements DataScopeAuthorizer, FieldUseSnapshot
{
    public function __construct(private CurrentWorkScope $work, private Factory $auth, private EntityRegistry $entities,
        private AccessChecker $checker, private ScopeResolver $visibility, private MatrixPermissions $matrix, private AuthOptions $options,
        private ConnectionResolverInterface $connections) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $principal = $this->principal($scope);

        if ($principal === null) {
            return new DataScopeAccessDecision(allowed: false, reasonCode: 'policy.denied');
        }
        $model = $this->model($record->entity);

        if ($model === null) {
            return new DataScopeAccessDecision(allowed: false, reasonCode: 'policy.denied');
        }
        $this->work->assert(scope: $scope, connection: $model->getConnection());
        $row = $model->newQuery()->find($record->id);

        if ($row instanceof Model) {
            foreach ($this->actions(fields: $fields, use: $use) as $action) {
                if (! $this->checker->canOnRecord(p: $principal, action: $action, record: $row)) {
                    return new DataScopeAccessDecision(allowed: false, reasonCode: 'policy.denied');
                }
            }

            return new DataScopeAccessDecision(allowed: true, reasonCode: 'policy.allowed');
        }

        return new DataScopeAccessDecision(allowed: false, reasonCode: 'policy.denied');
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $principal = $this->principal($scope);
        $model = $this->model($entity->value);

        if ($principal === null || $model === null) {
            return FieldUsePlan::denied();
        }
        $this->work->assert(scope: $scope, connection: $model->getConnection());
        $actions = $this->actions(fields: $request->fields, use: $request->use);
        $corpus = $model->newQuery()->toBase();
        $unconditional = true;
        foreach ($actions as $action) {
            if (! $this->checker->allowed(p: $principal, entityKey: $entity->value, action: $action)) {
                return FieldUsePlan::denied();
            }

            if (isset($this->options->policyPanels[$entity->value.'.'.$action]) || $model instanceof ProvidesAccessScope
                || (! $this->matrix->directlyGranted(principal: $principal, entity: $entity->value, action: $action) && $this->matrix->scope(principal: $principal, entity: $entity->value, action: $action) !== Scope::Account)) {
                $unconditional = false;
            }

            try {
                $visible = $this->visibility->apply(q: $model->newQuery(), p: $principal, entityKey: $entity->value, action: $action);
            } catch (ExactVisibilityUnavailable) {
                return FieldUsePlan::denied();
            }
            $corpus->whereIn($model->getQualifiedKeyName(), $visible->toBase()->select($model->getQualifiedKeyName()));
        }
        $context = [$scope, $entity->value, $actions];

        if ($unconditional) {
            return FieldUsePlan::unconditional(hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)));
        }
        $ids = [];
        foreach ($corpus->orderBy($model->getQualifiedKeyName())->limit(1001)->pluck($model->getQualifiedKeyName()) as $key) {
            if (! is_string($key) && ! is_int($key)) {
                return FieldUsePlan::denied();
            }
            $ids[] = (string) $key;
        }

        if (count($ids) > 1000) {
            return FieldUsePlan::denied();
        }

        return FieldUsePlan::scoped(witness: hash('sha256', json_encode([$context, $ids], JSON_THROW_ON_ERROR)), recordIds: $ids);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T|null
     */
    public function inSnapshot(DataScope $scope, EntityHandle $entity, Closure $read): mixed
    {
        $model = $this->model($entity->value);

        if ($model === null || $model instanceof ProvidesAccessScope || ! $this->checker instanceof PanelAccessChecker
            || $this->visibility !== $this->checker || $this->hasExternalPolicy($entity)) {
            return null;
        }
        $connection = $this->connections->connection();

        if (! $connection instanceof Connection || $connection !== $model->getConnection() || $connection->getDriverName() !== 'pgsql'
            || $connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
            return null;
        }
        $this->work->assert(scope: $scope, connection: $connection);

        try {
            return $connection->transaction(function () use ($scope, $connection, $read): mixed {
                $connection->statement("SET LOCAL transaction_isolation = 'repeatable read'");
                $this->work->assert(scope: $scope, connection: $connection);

                return $this->matrix->inSnapshot(connection: $connection, read: $read);
            });
        } catch (QueryException $failure) {
            if ($failure->getCode() !== '40001') {
                throw $failure;
            }

            return null;
        }
    }

    private function hasExternalPolicy(EntityHandle $entity): bool
    {
        $prefix = $entity->value.'.';

        return array_any(FieldUse::cases(), fn (FieldUse $use): bool => isset($this->options->policyPanels[$prefix.$use->value]))
            || array_any(array_keys($this->options->policyPanels), static fn (string $permission): bool => str_starts_with($permission, $prefix.'field.'));
    }

    private function principal(DataScope $scope): ?Principal
    {
        $this->work->assert($scope);
        $principal = $this->auth->guard()->user();

        return $principal instanceof Principal && $principal->principalId() === $scope->principalId
            && $principal->principalType()->value === $scope->principalKind ? $principal : null;
    }

    private function model(string $handle): ?Model
    {
        $type = $this->entities->has($handle) ? $this->entities->get($handle)->model : null;

        return $type !== null && is_subclass_of($type, Model::class) ? new $type : null;
    }

    /** @return list<string> */
    private function actions(FieldSelection $fields, FieldUse $use): array
    {
        $actions = [$use->value];
        foreach ($fields->fields as $id) {
            $actions[] = 'field.'.$id.'.'.$use->value;
        }

        return $actions;
    }
}
