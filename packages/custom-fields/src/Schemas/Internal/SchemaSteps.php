<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Schemas\Contracts\SchemaFacts;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Foundation\Laravel\ReferenceWrites;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class SchemaSteps
{
    public function __construct(private DatabaseManager $database, private CurrentWorkScope $current, private DatabaseSchemaHeads $heads,
        private SchemaJournal $journal, private SchemaAccess $access, private ShadowBackfill $backfill, private PostgresDdlExecutor $ddl,
        private SchemaFacts $facts, private SchemaReferences $references) {}

    public function run(DataScope $scope, stdClass $claim): void
    {
        $db = $this->database->connection();
        $this->current->assert($scope, $db);
        $entity = new EntityHandle(SchemaValue::text($claim->entity));
        $this->access->manage($scope, $entity);

        if ($claim->cancel_requested !== true) {
            // runStored retains this claim ticket through the dedicated CIC
            // dispatch and acknowledgment, beyond this local transaction.
            $db->transaction(fn () => ReferenceWrites::enlist($db, $scope, 'axiomasoft/corex-custom-fields',
                'schema-step', ['entity' => $entity->value, 'operation' => SchemaValue::text($claim->id)]));
        }
        $plan = SchemaRows::decode($claim->plan);

        if (SchemaRows::digest(['fields.plan.v2', SchemaRows::logical($scope), SchemaRows::fence($scope), $plan]) !== $claim->plan_digest) {
            throw new SchemaConflict('stored-plan-integrity');
        }
        $cancel = $claim->cancel_requested === true;

        if ($claim->phase === 'index' && ! $cancel) {
            $this->ddl->run($db, $scope, $claim, SchemaValue::map($plan['ddl']), null, function (?array $receipt) use ($db, $scope, $claim): void {
                $this->journal->advance($db, $scope, $claim, 'verifying', 'verify', ['ddl_receipt' => SchemaRows::json($receipt)]);
            });

            return;
        }

        if ($claim->phase === 'contract' || $cancel) {
            $cleanup = $db->transaction(function () use ($db, $scope, $entity, $claim, $plan, $cancel): ?array {
                $this->lockedHead($db, $scope, $entity, $claim);

                if (! $cancel && ($plan['drop_origin'] !== null || $plan['shadow'] === true || $plan['kind'] === 'retire')) {
                    $this->assertDrained($scope, $entity, $claim, $plan);
                }

                if ($cancel && $plan['ddl'] !== null && $claim->ddl_intent !== null) {
                    return [SchemaValue::map($plan['ddl']), ['digest' => SchemaValue::map($plan['ddl'])['digest'], 'cancel' => true,
                        'oid' => $claim->ddl_receipt === null ? null : SchemaRows::decode($claim->ddl_receipt)['oid']]];
                }

                if (! $cancel && $plan['drop_origin'] !== null) {
                    $old = $this->journal->operation($db, $scope, SchemaValue::text($plan['drop_origin']))->where('entity', $entity->value)->first();

                    if ($old === null || $old->ddl_receipt === null || $old->state !== 'completed') {
                        throw new SchemaConflict('old-index-owner-unproven');
                    }

                    return [SchemaValue::map(SchemaRows::decode($old->plan)['ddl']), SchemaRows::decode($old->ddl_receipt)];
                }
                $this->finish($db, $scope, $entity, $claim, $plan, $cancel);

                return null;
            });

            if ($cleanup !== null) {
                [$spec,$receipt] = $cleanup;
                $this->ddl->run($db, $scope, $claim, $spec, $receipt, function () use ($db, $scope, $entity, $claim, $plan, $cancel): void {
                    $this->finish($db, $scope, $entity, $claim, $plan, $cancel);
                });
            }

            return;
        }
        $db->transaction(function () use ($db, $scope, $entity, $claim, $plan): void {
            $head = $this->lockedHead($db, $scope, $entity, $claim);

            if ($claim->phase === 'expand') {
                $image = new SchemaImage([SchemaValue::text($plan['field']) => $plan['to']]);
                $id = SchemaValue::text($plan['field']);

                if ($plan['kind'] !== 'retire') {
                    $this->heads->reserve($db, $scope, $entity, $image, $id, SchemaValue::text($claim->id));
                }

                if ($plan['shadow'] === true) {
                    $rule = $this->dualRule($plan);
                    SchemaRows::one(SchemaRows::rows($db, SchemaRows::HEAD, $scope, $entity->value)->where('pending_plan', $claim->id)->update(['dual_write' => SchemaRows::json($rule)]));
                    $this->backfill->begin($db, $scope, $entity->value, $claim);
                    $this->journal->advance($db, $scope, $claim, 'backfilling', 'backfill');
                } else {
                    $this->afterBackfill($db, $scope, $claim, $plan);
                }

                return;
            }

            if ($claim->phase === 'backfill') {
                $phase = $this->backfill->batch($db, $scope, $claim, $plan);

                if ($phase === 'verify') {
                    $this->afterBackfill($db, $scope, $claim, $plan);
                } else {
                    $this->journal->advance($db, $scope, $claim, $phase === 'blocked' ? 'blocked' : 'backfilling', 'backfill',
                        ['blockers' => $phase === 'blocked' ? '["historical-conversion-failed"]' : '[]']);
                }

                return;
            }

            if (! in_array($claim->phase, ['verify', 'switch'], true)) {
                throw new SchemaConflict('unknown-schema-phase');
            }

            if ($this->heads->declared($db, $scope, $entity)->digest() !== $plan['deployed_manifest_digest']) {
                throw new SchemaConflict('declaration-manifest-changed');
            }

            if ($plan['kind'] === 'retire' || $plan['shadow'] === true) {
                $this->assertDrained($scope, $entity, $claim, $plan);
            }

            if ($plan['shadow'] === true) {
                if ($head->dual_write === null || SchemaRows::digest(SchemaRows::decode($head->dual_write)) !== SchemaRows::digest($this->dualRule($plan))) {
                    throw new SchemaConflict('dual-write-rule-changed');
                }

                if (! $this->backfill->verify($db, $scope, $claim, $plan)) {
                    $this->journal->advance($db, $scope, $claim, 'verifying', 'verify');

                    return;
                }
            }

            $this->verifyTargetIndex($db, $scope, $claim, $plan);

            if ($claim->phase === 'verify') {
                $this->journal->advance($db, $scope, $claim, 'verifying', 'switch');

                return;
            }
            $id = SchemaValue::text($plan['field']);
            $revision = new SchemaImage([$id => $plan['to']]);
            $this->heads->publishDefinition($db, $scope, $entity, $revision, $id);
            SchemaRows::one(SchemaRows::rows($db, SchemaRows::HEAD, $scope, $entity->value)->where('pending_plan', $claim->id)
                ->where('effective_revision', $head->effective_revision)->where('mapping_digest', $plan['base_mapping_digest'])->update([
                    'mapping' => SchemaRows::json($plan['mapping']), 'effective_revision' => $plan['target'], 'mapping_digest' => $plan['mapping_digest'],
                    'manifest_digest' => $plan['manifest_digest'], 'dual_write' => null]));
            $this->facts->changed($db, $scope, $entity, SchemaValue::text($claim->base_revision), SchemaValue::text($claim->target_revision),
                SchemaValue::text($plan['mapping_digest']), SchemaValue::text($claim->changed_message), SchemaValue::text($claim->requested_message), SchemaValue::text($claim->id));
            $this->journal->advance($db, $scope, $claim, 'switched', 'contract');
        });
    }

    /**
     * @param  array<string,mixed>  $plan
     * @return array<string,mixed>
     */
    private function dualRule(array $plan): array
    {
        $entry = SchemaValue::map($plan['to']);

        return ['field' => $plan['field'], 'slot' => $entry['slot'], 'definition' => $entry['definition'],
            'converter_id' => $plan['converter_id'], 'converter_version' => $plan['converter_version']];
    }

    /** @param array<string,mixed> $plan */
    private function verifyTargetIndex(Connection $db, DataScope $scope, stdClass $claim, array $plan): void
    {
        $origin = SchemaValue::map($plan['to'])['index_operation'];

        if ($origin === null || $plan['kind'] === 'retire') {
            return;
        }
        $owner = $origin === $claim->id ? $claim
            : $this->journal->operation($db, $scope, SchemaValue::text($origin))->where('entity', $claim->entity)->where('state', 'completed')->first();

        if ($owner === null || $owner->ddl_receipt === null) {
            throw new SchemaConflict('index-owner-unproven');
        }
        $spec = SchemaValue::map(SchemaRows::decode($owner->plan)['ddl']);
        $receipt = SchemaRows::decode($owner->ddl_receipt);
        IndexCatalogue::verifyParent($db, $spec);
        $index = IndexCatalogue::inspect($db, $spec);

        if ($index === null || ! $index->indisready || ! $index->indisvalid || ! $index->indislive ||
            $receipt['oid'] !== $index->oid || $receipt['digest'] !== $spec['digest']) {
            throw new SchemaConflict('index-identity-changed');
        }
    }

    private function lockedHead(Connection $db, DataScope $scope, EntityHandle $entity, stdClass $claim): stdClass
    {
        $head = $this->heads->row($db, $scope, $entity, $claim->phase !== 'backfill' || $claim->cancel_requested === true, true);

        if ($head->pending_plan !== $claim->id || $head->effective_revision !== ($claim->phase === 'contract' ? $claim->target_revision : $claim->base_revision)) {
            throw new SchemaConflict('schema-head-generation-conflict');
        }
        $this->journal->locked($db, $scope, $claim);

        return $head;
    }

    /** @param array<string,mixed> $plan */
    private function afterBackfill(Connection $db, DataScope $scope, stdClass $claim, array $plan): void
    {
        $index = $plan['ddl'] !== null;
        $this->journal->advance($db, $scope, $claim, $index ? 'expanding' : 'verifying', $index ? 'index' : 'verify');
    }

    /** @param array<string,mixed> $plan */
    private function assertDrained(DataScope $scope, EntityHandle $entity, stdClass $claim, array $plan): void
    {
        if ($this->references->get($scope, $entity, SchemaValue::text($plan['field']), SchemaValue::text($claim->base_revision)) !== []) {
            throw new SchemaConflict('dependent-owner-references');
        }
    }

    /** @param array<string,mixed> $plan */
    private function finish(Connection $db, DataScope $scope, EntityHandle $entity, stdClass $claim, array $plan, bool $cancel): void
    {
        // All local readers hold this head through data capture. Its exclusive lock has drained their old mapping.
        $head = $this->lockedHead($db, $scope, $entity, $claim);
        $id = SchemaValue::text($plan['field']);
        $old = $plan['from'] === null ? null : SchemaValue::map($plan['from']);
        $new = SchemaValue::map($plan['to']);
        $discard = $cancel ? $new : $old;

        if ($discard !== null) {
            $hist = new SchemaImage([$id => $discard]);
            $effective = new SchemaImage(SchemaRows::decode($head->mapping));
            $visible = isset($effective->entries[$id]) ? $effective->entry($id) : null;

            if ($visible === null || $discard['slot'] !== $visible['slot']) {
                if ($cancel && $head->dual_write !== null) {
                    SchemaRows::one(SchemaRows::rows($db, SchemaRows::HEAD, $scope, $entity->value)->where('pending_plan', $claim->id)->update(['dual_write' => null]));
                }

                if (! $this->backfill->discard($db, $scope, $entity->value, $claim, SchemaValue::text($discard['slot']))) {
                    $this->journal->advance($db, $scope, $claim, $cancel ? 'expanding' : 'contracting', SchemaValue::text($claim->phase));

                    return;
                }
            }

            if ($visible === null || $effective->definition($id)->machineKey !== $hist->definition($id)->machineKey) {
                $reservations = SchemaRows::rows($db, SchemaRows::RESERVATIONS, $scope, $entity->value)->where('field_id', $id)->where('machine_key', $hist->definition($id)->machineKey);

                if ($cancel) {
                    $reservations->where('operation_id', $claim->id);
                }
                $reservations->delete();
            }
        }
        SchemaRows::one(SchemaRows::rows($db, SchemaRows::HEAD, $scope, $entity->value)->where('pending_plan', $claim->id)->update(['dual_write' => null, 'pending_plan' => null]));
        $this->journal->advance($db, $scope, $claim, $cancel ? 'cancelled' : 'completed', 'completed');
    }
}
