<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Contracts\Scopes\ProvidesAccessScope;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ExactVisibilityUnavailable;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\Principal;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;

#[Internal]
final readonly class ScopedFieldPolicy implements DataScopeAuthorizer, FieldUsePolicy
{
    public function __construct(private CurrentWorkScope $work, private Factory $auth, private EntityRegistry $entities,
        private AccessChecker $checker, private ScopeResolver $visibility, private MatrixPermissions $matrix, private AuthOptions $options) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $principal = $this->principal($scope);

        if ($principal === null) {
            return new DataScopeAccessDecision(false, 'policy.denied');
        }
        $model = $this->model($record->entity);

        if ($model === null) {
            return new DataScopeAccessDecision(false, 'policy.denied');
        }
        $this->work->assert($scope, $model->getConnection());
        $row = $model->newQuery()->find($record->id);

        if ($row instanceof Model) {
            foreach ($this->actions($fields, $use) as $action) {
                if (! $this->checker->canOnRecord($principal, $action, $row)) {
                    return new DataScopeAccessDecision(false, 'policy.denied');
                }
            }

            return new DataScopeAccessDecision(true, 'policy.allowed');
        }

        return new DataScopeAccessDecision(false, 'policy.denied');
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $principal = $this->principal($scope);
        $model = $this->model($entity->value);

        if ($principal === null || $model === null) {
            return FieldUsePlan::denied();
        }
        $this->work->assert($scope, $model->getConnection());
        $actions = $this->actions($request->fields, $request->use);
        $corpus = $model->newQuery()->toBase();
        $unconditional = true;
        foreach ($actions as $action) {
            if (! $this->checker->allowed($principal, $entity->value, $action)) {
                return FieldUsePlan::denied();
            }

            if (isset($this->options->policyPanels[$entity->value.'.'.$action]) || $model instanceof ProvidesAccessScope
                || (! $this->matrix->directlyGranted($principal, $entity->value, $action) && $this->matrix->scope($principal, $entity->value, $action) !== Scope::Account)) {
                $unconditional = false;
            }

            try {
                $visible = $this->visibility->apply($model->newQuery(), $principal, $entity->value, $action);
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

        return FieldUsePlan::scoped(hash('sha256', json_encode([$context, $ids], JSON_THROW_ON_ERROR)), $ids);
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
