<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use Illuminate\Database\DatabaseManager;

final readonly class FieldScenarioPolicy implements FieldUsePolicy
{
    public function __construct(private CurrentWorkScope $current, private DatabaseManager $databases) {}

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $database = $this->databases->connection();
        $this->current->assert($scope, $database);
        $fields = $request->fields->fields ?: ['record'];
        $ids = $database->table('scenario_field_grants')->where([
            'principal_id' => $scope->principalId, 'workspace_id' => $scope->workspaceId, 'entity' => $entity->value, 'field_use' => $request->use->value,
        ])->whereIn('field_id', $fields)->groupBy('record_id')->havingRaw('COUNT(*) = ?', [count($fields)])
            ->orderBy('record_id')->pluck('record_id')->all();

        if ($ids === []) {
            return FieldUsePlan::denied();
        }

        return FieldUsePlan::scoped(hash('sha256', json_encode([$entity->value, $scope->principalId, $request->use->value, $fields, $ids], JSON_THROW_ON_ERROR)), $ids);
    }
}
