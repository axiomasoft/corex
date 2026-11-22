<?php

declare(strict_types=1);

namespace App\ProductProof;

use App\BulkTransfer\BulkAuthority;
use App\Scenario\Scenario;
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
use Equipment\Registry\Equipment;
use Illuminate\Support\Facades\DB;

final readonly class EquipmentAccess implements DataScopeAuthorizer, FieldUsePolicy
{
    public function __construct(private BulkAuthority $bulk, private CurrentWorkScope $current) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $db = DB::connection();
        $this->current->assert($scope, $db);

        if ($record->entity === 'task-series') {
            $asset = $db->table('installed_equipment_series')->where('id', $record->id)->value('asset_key');
            $granted = is_string($asset) && $this->bulk->authorize(
                scope: $scope, record: new RecordRef(Equipment::Handle, $asset), fields: $fields, use: $use,
            )->allowed;

            return new DataScopeAccessDecision($granted, $granted ? 'equipment.series.granted' : 'equipment.series.denied');
        }

        if ($record->entity === Equipment::Handle && $scope->workspaceId !== Scenario::W1) {
            return new DataScopeAccessDecision(false, 'equipment.owner.workspace-denied');
        }

        return $this->bulk->authorize(scope: $scope, record: $record, fields: $fields, use: $use);
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $db = DB::connection();
        $this->current->assert($scope, $db);

        if ($entity->value !== Equipment::Handle || $scope->workspaceId !== Scenario::W1 || $scope->principalId !== Scenario::USER_A) {
            return FieldUsePlan::denied();
        }
        $fields = $db->table('scenario_field_grants')->where([
            'principal_id' => $scope->principalId, 'workspace_id' => $scope->workspaceId,
            'entity' => Equipment::Handle, 'field_use' => $request->use->value,
        ])->orderBy('field_id')->pluck('field_id')->all();
        $allowed = in_array('record', $fields, true) && array_diff($request->fields->fields, $fields) === [];

        return $allowed ? FieldUsePlan::unconditional(hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR))) : FieldUsePlan::denied();
    }
}
