<?php

declare(strict_types=1);

namespace App\FieldSearch;

use App\Scenario\Scenario;
use App\Scenario\SearchScenarioData;
use CoreX\Context\Contracts\CurrentWorkScope;
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
use Illuminate\Support\Facades\DB;

final readonly class EquipmentAccess implements DataScopeAuthorizer, FieldUsePolicy
{
    public function __construct(private CurrentWorkScope $scope) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $this->scope->assert($scope, DB::connection());
        $allowed = ($record->entity === 'fields.schema' && $record->id === EquipmentOwner::ENTITY
                && $this->operator($scope) && in_array($use, [FieldUse::Read, FieldUse::Write], true))
            || ($record->entity === EquipmentOwner::ENTITY && $this->grants($scope, $fields, $use)
                && EquipmentOwner::records($scope)->where('asset_key', $record->id)->exists());

        return new DataScopeAccessDecision($allowed, $allowed ? 'equipment.granted' : 'equipment.denied');
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $this->scope->assert($scope, DB::connection());

        if ($entity->value !== EquipmentOwner::ENTITY || ! $this->grants($scope, $request->fields, $request->use)) {
            return FieldUsePlan::denied();
        }

        return FieldUsePlan::unconditional(hash('sha256', json_encode([
            $scope->workspaceId, $scope->principalId, $request->use->value, $request->fields->fields,
        ], JSON_THROW_ON_ERROR)));
    }

    private function grants(DataScope $scope, FieldSelection $fields, FieldUse $use): bool
    {
        if ($scope->accountId !== Scenario::BOXED || $scope->workspaceId !== Scenario::W1) {
            return false;
        }

        if ($scope->principalKind === 'service' && $scope->principalId === SearchScenarioData::SERVICE) {
            return $use === FieldUse::Read;
        }

        return $this->operator($scope) && ! in_array(EquipmentLocaleSource::PRIVATE_ID, $fields->fields, true);
    }

    private function operator(DataScope $scope): bool
    {
        return $scope->accountId === Scenario::BOXED && $scope->workspaceId === Scenario::W1
            && $scope->principalKind === 'user' && $scope->principalId === Scenario::USER_A;
    }
}
