<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\OperationKey;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Contracts\SchemaFacts;
use CoreX\CustomFields\Schemas\Data\ClaimGeneration;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\PlanDigest;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Schemas\Data\SchemaOperationStatus;
use CoreX\CustomFields\Schemas\Data\SchemaPlan;
use CoreX\CustomFields\Schemas\Data\SchemaPlanRef;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;
use CoreX\Foundation\Laravel\ReferenceWrites;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class DatabaseFieldSchemas implements FieldSchemas
{
    public function __construct(private DatabaseManager $database, private WorkScopeRunner $runner, private CurrentWorkScope $current,
        private StoredWorkIssuer $origins, private DataScopeCodec $codec, private DatabaseSchemaHeads $heads, private SchemaAccess $access,
        private SchemaPlanner $planner, private SchemaReferences $references, private SchemaJournal $journal, private SchemaFacts $facts, private ClockInterface $clock) {}

    public function plan(DataScope $scope, FieldSchemaChange $change, SchemaRevision $expected): SchemaPlan
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $change, $expected): SchemaPlan {
            $db = $this->database->connection();

            return $db->transaction(function () use ($db, $scope, $change, $expected): SchemaPlan {
                $this->current->assert(scope: $scope, connection: $db);
                $this->access->manage(scope: $scope, entity: $change->entity);
                ReferenceWrites::enlist(connection: $db, scope: $scope, producer: 'axiomasoft/corex-custom-fields', kind: 'schema-plan', witness: ['entity' => $change->entity->value, 'change' => $change]);
                $head = $this->heads->row(db: $db, scope: $scope, entity: $change->entity, allowManifestChange: true);

                if ($head->effective_revision !== $expected->value) {
                    throw new SchemaConflict('stale-base-revision');
                }
                $ref = (string) Str::uuid7();
                $plan = $this->planner->build(db: $db, scope: $scope, head: $head, change: $change, ref: $ref);
                $digest = SchemaRows::digest(['fields.plan.v2', SchemaRows::logical($scope), SchemaRows::fence($scope), $plan]);
                $db->table('sys_attribute_schema_operations')->insert([...SchemaRows::logical($scope), ...SchemaRows::fence($scope), 'entity' => $change->entity->value,
                    'id' => $ref, 'operation_key' => 'P/'.$ref, 'plan' => SchemaRows::json($plan), 'plan_digest' => $digest,
                    'base_revision' => $expected->value, 'target_revision' => $plan['target'], 'scope' => SchemaRows::json($this->codec->encode($scope)),
                    'requested_message' => (string) Str::uuid7(), 'changed_message' => (string) Str::uuid7(), 'state' => 'planned', 'phase' => 'expand', 'blockers' => SchemaRows::json($this->blockers($plan))]);

                $ddl = $plan['ddl'] === null ? null : SchemaValue::map($plan['ddl']);

                return new SchemaPlan(ref: new SchemaPlanRef($ref), digest: new PlanDigest($digest), base: $expected, target: new SchemaRevision(SchemaValue::text($plan['target'])),
                    blockers: $this->blockers($plan), estimatedRows: SchemaValue::integer($plan['estimated_rows']), change: $change,
                    ddlDigest: $ddl === null ? null : new PlanDigest(SchemaValue::text($ddl['digest'])), dependencies: SchemaValue::strings($plan['dependencies']),
                    conversionFailurePolicy: SchemaValue::text($plan['failure_policy']));
            });
        });
    }

    /**
     * @param  array<string,mixed>  $plan
     * @return list<string>
     */
    private function blockers(array $plan): array
    {
        return ($plan['kind'] === 'retire' || $plan['shadow'] === true) ? SchemaValue::strings($plan['dependencies']) : [];
    }

    public function apply(DataScope $scope, SchemaPlanRef $plan, PlanDigest $expected, OperationKey $key): SchemaOperationRef
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $plan, $expected, $key): SchemaOperationRef {
            $db = $this->database->connection();

            return $db->transaction(function () use ($db, $scope, $plan, $expected, $key): SchemaOperationRef {
                $this->current->assert(scope: $scope, connection: $db);
                $query = $this->journal->operation(db: $db, scope: $scope, id: $plan->id);
                $entity = new EntityHandle(SchemaValue::text((clone $query)->value('entity') ?? throw new SchemaConflict('unknown-plan')));
                $this->access->manage(scope: $scope, entity: $entity);
                $head = $this->heads->row(db: $db, scope: $scope, entity: $entity, exclusive: true, allowManifestChange: true);
                $row = (clone $query)->lockForUpdate()->first() ?? throw new SchemaConflict('unknown-plan');
                $body = SchemaRows::decode($row->plan);
                ReferenceWrites::enlist(connection: $db, scope: $scope, producer: 'axiomasoft/corex-custom-fields', kind: 'schema-apply', witness: ['entity' => $entity->value, 'plan' => $body]);

                if ($row->plan_digest !== $expected->value || $expected->value !== SchemaRows::digest(['fields.plan.v2', SchemaRows::logical($scope), SchemaRows::fence($scope), $body])
                    || ($key->deadline !== null && $key->deadline <= $this->clock->now())) {
                    throw new SchemaConflict('plan-digest-or-deadline');
                }
                $keyValue = 'A/'.$key->id;
                $previous = SchemaRows::rows(db: $db, table: SchemaRows::OPERATIONS, scope: $scope, entity: $entity->value)->where('operation_key', $keyValue)->first();

                if ($previous !== null) {
                    if ($previous->id !== $row->id || $previous->plan_digest !== $expected->value) {
                        throw new SchemaConflict(reason: 'schema-operation-key-conflict', keyConflict: true);
                    }

                    return new SchemaOperationRef($plan->id);
                }

                if ($this->blockers($body) !== [] || (($body['shadow'] === true || $body['kind'] === 'retire')
                    && $this->references->get(scope: $scope, entity: $entity, field: SchemaValue::text($body['field']), revision: SchemaValue::text($body['base'])) !== []) || $row->state !== 'planned' || $head->pending_plan !== null || $head->effective_revision !== $row->base_revision
                    || $head->mapping_digest !== $body['base_mapping_digest'] || $this->heads->declared(db: $db, scope: $scope, entity: $entity)->digest() !== $body['deployed_manifest_digest']) {
                    throw new SchemaConflict('stale-or-pending-schema');
                }
                $proof = $this->origins->issue(scope: $scope, owner: SchemaJournal::OWNER, reference: SchemaJournal::proofReference($row));
                SchemaRows::one($query->where('state', 'planned')->update(['operation_key' => $keyValue, 'scope_seal' => $proof->seal,
                    'scope' => SchemaRows::json($this->codec->encode($scope)), 'state' => 'queued', 'next_attempt_at' => $this->journal->now()]));
                SchemaRows::one(SchemaRows::rows(db: $db, table: SchemaRows::HEAD, scope: $scope, entity: $entity->value)->whereNull('pending_plan')->update(['pending_plan' => $row->id]));
                $this->facts->requested(db: $db, scope: $scope, entity: $entity, plan: $plan->id, digest: $expected->value, messageId: SchemaValue::text($row->requested_message));

                return new SchemaOperationRef($plan->id);
            });
        });
    }

    public function inspect(DataScope $scope, SchemaOperationRef $operation): SchemaOperationStatus
    {
        return $this->control(scope: $scope, operation: $operation, expected: null, command: null);
    }

    public function retry(DataScope $scope, SchemaOperationRef $operation, ClaimGeneration $expected): SchemaOperationStatus
    {
        return $this->control(scope: $scope, operation: $operation, expected: $expected, command: 'retry');
    }

    public function cancel(DataScope $scope, SchemaOperationRef $operation, ClaimGeneration $expected): SchemaOperationStatus
    {
        return $this->control(scope: $scope, operation: $operation, expected: $expected, command: 'cancel');
    }

    private function control(DataScope $scope, SchemaOperationRef $operation, ?ClaimGeneration $expected, ?string $command): SchemaOperationStatus
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $operation, $expected, $command): SchemaOperationStatus {
            $db = $this->database->connection();

            return $db->transaction(function () use ($db, $scope, $operation, $expected, $command): SchemaOperationStatus {
                $this->current->assert(scope: $scope, connection: $db);
                $query = $this->journal->operation(db: $db, scope: $scope, id: $operation->id);
                $entity = new EntityHandle(SchemaValue::text((clone $query)->value('entity') ?? throw new SchemaConflict('unknown-operation')));
                $this->access->manage(scope: $scope, entity: $entity, use: $command === null ? FieldUse::Read : FieldUse::Write);
                $head = $this->heads->row(db: $db, scope: $scope, entity: $entity, exclusive: $command !== null, allowManifestChange: true);
                $row = (clone $query)->lockForUpdate()->first() ?? throw new SchemaConflict('unknown-operation');

                if ($expected === null) {
                    return $this->journal->status(db: $db, row: $row);
                }

                if ($row->generation !== $expected->value && SchemaValue::integer($row->generation) !== $expected->value) {
                    throw new SchemaClaimLost('Stale operation generation.');
                }
                $postSwitch = in_array($row->phase, ['contract', 'completed'], true);

                if ($head->pending_plan !== $row->id || $head->effective_revision !== ($postSwitch ? $row->target_revision : $row->base_revision)
                    || ($command === 'cancel' && $postSwitch) || ($command === 'retry' && ! in_array($row->state, ['blocked', 'failed'], true))) {
                    throw new SchemaConflict('operation-transition-unavailable');
                }

                if ($command === 'retry') {
                    $uncorrected = $db->table('sys_attribute_backfills as f')->join('sys_attribute_documents as d', 'd.record_id', '=', 'f.record_id')
                        ->where('f.operation_id', $row->id)->where('f.kind', 'error')->where('d.entity', $entity->value)->whereColumn('d.document_revision', 'f.seen_revision');
                    foreach (SchemaRows::logical($scope) as $column => $value) {
                        $uncorrected->where('d.'.$column, $value);
                    }

                    if ($uncorrected->exists()) {
                        throw new SchemaConflict('historical-conversion-still-failing');
                    }
                }
                $next = match ($row->phase) {
                    'expand' => 'queued','backfill' => 'backfilling','index' => 'expanding','verify','switch' => 'verifying','contract' => 'contracting',default => 'expanding',
                };
                SchemaRows::one((clone $query)->where('generation', $expected->value)->update(['generation' => $expected->value + 1, 'claim_token' => null, 'lease_until' => null,
                    'state' => $next, 'cancel_requested' => $command === 'cancel', 'blockers' => '[]', 'next_attempt_at' => $this->journal->now()]));

                return $this->journal->status(db: $db, row: $query->first() ?? throw new SchemaConflict('unknown-operation'));
            });
        });
    }
}
