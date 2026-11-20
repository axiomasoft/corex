<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Database\DatabaseManager;

final readonly class ScenarioAuthorization implements DataScopeAuthorizer
{
    public function __construct(private CurrentWorkScope $current, private DatabaseManager $database) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $connection = $this->database->connection();
        $this->current->assert($scope, $connection);
        $grant = $connection->table('scenario_field_grants')->where('principal_id', $scope->principalId)
            ->where('workspace_id', $scope->workspaceId ?? 'account')->where('entity', $record->entity)
            ->where('record_id', $record->id)->where('field_use', $use->value);
        $required = $fields->fields === [] ? ['record'] : $fields->fields;
        $allowed = $grant->whereIn('field_id', $required)->count() === count($required);

        return new DataScopeAccessDecision($allowed, $allowed ? 'scenario.granted' : 'scenario.denied');
    }
}
