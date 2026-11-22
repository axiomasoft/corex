<?php

declare(strict_types=1);

namespace App\TransportProof;

use App\BulkTransfer\BulkAuthority;
use App\FieldSearch\EquipmentLocaleSource;
use App\Scenario\Scenario;
use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
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
use Equipment\Registry\Equipment;
use Illuminate\Support\Facades\DB;

final readonly class EquipmentFieldsPolicy implements DataScopeAuthorizer, FieldUseSnapshot
{
    public function __construct(private CurrentWorkScope $current, private BulkAuthority $bulk) {}

    public function inSnapshot(DataScope $scope, EntityHandle $entity, Closure $read): mixed
    {
        $connection = DB::connection();

        if ($entity->value !== Equipment::Handle || $connection->getDriverName() !== 'pgsql'
            || $connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
            return null;
        }

        $this->current->assert(scope: $scope, connection: $connection);
        $barrier = getenv('COREX_SCENARIO_METADATA_SNAPSHOT_BARRIER');

        if (is_string($barrier)) {
            Scenario::barrier($barrier, [
                'connection' => Scenario::describe($connection),
                'grant' => $this->readable($scope),
                'level' => $connection->transactionLevel(),
            ]);
        }

        return $connection->transaction(function () use ($scope, $connection, $read, $barrier): mixed {
            $connection->statement("SET LOCAL transaction_isolation = 'repeatable read'");
            $this->current->assert(scope: $scope, connection: $connection);

            if (is_string($barrier)) {
                Scenario::barrier($barrier.'-snapshot', [
                    'connection' => Scenario::describe($connection),
                    'grant' => $this->readable($scope),
                    'level' => $connection->transactionLevel(),
                    'isolation' => $connection->scalar('SHOW transaction_isolation'),
                ]);
            }

            return $read();
        });
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        if ($record->entity === 'fields.schema' && $record->id === Equipment::Handle) {
            $allowed = $this->readable($scope);

            return new DataScopeAccessDecision($allowed, $allowed ? 'installed.equipment.granted' : 'installed.equipment.denied');
        }

        if ($record->entity === Equipment::Handle && $scope->workspaceId === Scenario::W1 && $use === FieldUse::Read
            && $this->readable($scope) && DB::table('eq_equipment')->where('asset_key', $record->id)->whereNull('deleted_at')->exists()) {
            $allowed = $scope->principalKind === 'service' || ! $this->privateField($fields);

            return new DataScopeAccessDecision($allowed, $allowed ? 'installed.equipment.readable' : 'installed.equipment.denied');
        }

        return $this->bulk->authorize(scope: $scope, record: $record, fields: $fields, use: $use);
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        if ($entity->value !== Equipment::Handle || ! $this->readable($scope)
            || ($scope->principalKind === 'service' ? $request->use !== FieldUse::Read : $this->privateField($request->fields))) {
            return FieldUsePlan::denied();
        }

        return FieldUsePlan::unconditional(hash('sha256', json_encode([
            $scope->workspaceId, $scope->principalId, $request->use, $request->fields,
        ], JSON_THROW_ON_ERROR)));
    }

    private function readable(DataScope $scope): bool
    {
        $this->current->assert($scope, DB::connection());

        if ($scope->accountId !== Scenario::BOXED || $scope->workspaceId !== Scenario::W1) {
            return false;
        }

        if ($scope->principalKind === 'service') {
            return $scope->principalId === WorkerAuthority::SEARCH
                && DB::table('installed_metadata_workers')->where([
                    'account_id' => $scope->accountId, 'workspace_id' => $scope->workspaceId, 'service' => $scope->principalId,
                ])->exists();
        }

        return DB::table('installed_transport_grants')->where([
            'workspace_id' => $scope->workspaceId, 'principal_id' => $scope->principalId, 'grant' => 'equipment-schema',
        ])->exists();
    }

    private function privateField(FieldSelection $selection): bool
    {
        return array_any($selection->fields, static fn (string $field): bool => str_contains($field, EquipmentLocaleSource::PRIVATE_ID));
    }
}
