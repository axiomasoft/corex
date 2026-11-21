<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\Principal;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;

final readonly class BulkAuthority implements ClientScopeAuthorizer, DataScopeAuthorizer, OperationAuthorizer, PrincipalResolver
{
    public function __construct(private CurrentWorkScope $current, private DatabaseManager $database) {}

    public function resolve(Request $request): ?Principal
    {
        $principal = $request->user();

        return $principal instanceof Principal ? $principal : null;
    }

    public function allows(Operation $operation, RequestContext $context): bool
    {
        $declared = match ($operation->module) {
            BulkDeclaration::MODULE => in_array($operation->id, ['fixture.bulk', 'fixture.ping'], true),
            'equipment/registry' => $operation->id === 'equipment.bulk.transfer',
            default => false,
        };

        return $declared && $context->tenant()->id() === Scenario::BOXED
            && $context->principal()->principalId() === Scenario::USER_A;
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $connection = $this->database->connection();
        $this->current->assert($scope, $connection);
        // The fixture assigns owner-namespace rights independently from equipment record and field rights.
        $resource = in_array($record->entity, ['import-export.transfer', 'import-export.manifest'], true) ? '*' : $record->id;
        $grants = $connection->table('scenario_field_grants')->where([
            'principal_id' => $scope->principalId, 'workspace_id' => $scope->workspaceId ?? 'account',
            'entity' => $record->entity, 'record_id' => $resource, 'field_use' => $use->value,
        ])->pluck('field_id')->all();
        $allowed = in_array('record', $grants, true) && array_diff($fields->fields, $grants) === [];

        return new DataScopeAccessDecision($allowed, $allowed ? 'fixture.granted' : 'fixture.denied');
    }
}
