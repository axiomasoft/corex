<?php

declare(strict_types=1);

namespace App\Scenario;

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

final readonly class SearchScenarioAccess implements DataScopeAuthorizer, FieldUsePolicy
{
    public function __construct(private (DataScopeAuthorizer&FieldUsePolicy)|null $native = null) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        if ($scope->principalKind !== 'service' && $this->native !== null) {
            return $this->native->authorize($scope, $record, $fields, $use);
        }
        $allowed = $this->indexReader($scope, new EntityHandle($record->entity), $use)
            && SearchScenarioData::rows($scope, new EntityHandle($record->entity))->where('id', $record->id)->exists();

        return new DataScopeAccessDecision($allowed, $allowed ? 'scenario.service-projection' : 'scenario.denied');
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        if ($scope->principalKind !== 'service' && $this->native !== null) {
            return $this->native->plan($scope, $entity, $request);
        }

        if (! $this->indexReader($scope, $entity, $request->use)) {
            return FieldUsePlan::denied();
        }
        SearchScenarioData::rows($scope, $entity);

        return FieldUsePlan::unconditional(hash('sha256', json_encode([
            $scope->accountId, $scope->workspaceId, $scope->principalId, $request->fields->fields,
        ], JSON_THROW_ON_ERROR)));
    }

    private function indexReader(DataScope $scope, EntityHandle $entity, FieldUse $use): bool
    {
        return $scope->principalKind === 'service' && $scope->principalId === SearchScenarioData::SERVICE
            && $entity->value === SearchScenarioRecord::ENTITY && $use === FieldUse::Read;
    }
}
