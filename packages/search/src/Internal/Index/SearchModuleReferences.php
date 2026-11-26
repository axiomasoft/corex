<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceCleanup;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\CleanupReceipt;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexNamespaceExclusion;
use CoreX\Search\Index\Contracts\IndexRemovalEvidence;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\Search\Internal\Value;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class SearchModuleReferences implements ModuleReferences, ReferenceCleanup
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current, private LaneStore $store, private Application $app) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-search';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);
        $child = static function (Builder $query, DataScope $scope) use ($tenant): void {
            $lanes = $tenant->table(LaneStore::LANES);

            if ($scope->workspaceId === null && (clone $query)->whereNotIn('lane_key', (clone $lanes)->select('lane_key'))->exists()) {
                throw new LifecycleBlocked('Unowned search generation, task or lane journal.');
            }
            $query->whereIn('lane_key', $lanes->select('lane_key')->where([
                'account_id' => $scope->accountId, 'account_incarnation' => $scope->accountIncarnation,
                'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId,
            ]));
        };

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            LaneStore::LANES => [$tenant, ['entity', 'active_generation', 'writer_generation', 'rebuild_id'], fn (array $row, string $id): ?LifecycleBlocker => $row['active_generation'] === null && $row['rebuild_id'] === null ? null
                : new LifecycleBlocker(owner: $this->owner(), resourceKind: 'search-lane', resourceId: $id, scope: $scope, requiredAction: 'retire-or-rebind-active-lane')],
            LaneStore::DIRTY => [$tenant, ['source', 'lane_key'], null, $child],
            LaneStore::RUNS => [$tenant, ['state', 'lane_key'], null, $child],
            LaneStore::GENERATIONS => [$tenant, ['generation', 'uid', 'state', 'lane_key'], function (array $row) use ($scope, $module): CleanupAction|LifecycleBlocker {
                $uid = Value::text($row['uid']);
                $state = Value::text($row['state']);

                if (! in_array($state, ['retired', 'cleanup', 'removed'], true)) {
                    return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'search-generation', resourceId: $uid, scope: $scope, requiredAction: 'drain-or-retire-generation');
                }
                $target = $this->target(scope: $scope, uid: $uid);
                $host = $this->app->bound(IndexNamespaceExclusion::class) ? $this->app->make(IndexNamespaceExclusion::class) : null;

                if ($host === null || ! in_array($host->owner(), $this->app->make(LifecycleInventory::class)->owners($scope, $module), true)) {
                    return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'search-generation', resourceId: $uid, scope: $scope, requiredAction: 'restore-exact-index-namespace-exclusion');
                }

                return $this->action($target);
            }, $child],
            LaneStore::SUBMISSIONS => [$tenant, ['generation', 'uid', 'task_ref', 'provider_ticket', 'state', 'control_recorded', 'lane_key'], function (array $row, string $id) use ($scope): ?LifecycleBlocker {
                $state = Value::text($row['state']);

                if (str_starts_with($state, 'cleanup-') || (in_array($state, ['confirmed', 'failed', 'cancelled', 'not-sent'], true)
                    && ($row['provider_ticket'] === null || $row['control_recorded'] === true))) {
                    return null;
                }

                return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'search-task', resourceId: Value::text($row['task_ref'] ?? $row['provider_ticket'] ?? $id), scope: $scope, requiredAction: 'reconcile-index-submission');
            }, $child],
        ], module: $module, scope: $scope, after: $after);
    }

    private function target(DataScope $scope, string $uid): IndexTarget
    {
        $db = $this->store->db($scope);
        $gen = $db->table(LaneStore::GENERATIONS)->where(['uid' => $uid, 'cluster' => $this->store->cluster])->firstOrFail();
        $lane = $db->table(LaneStore::LANES)->where($this->store->identity($scope))->where('lane_key', $gen->lane_key)->firstOrFail();
        $entity = new EntityHandle(Value::text($lane->entity));

        if ($lane->lane_key !== $this->store->laneIdentity(scope: $scope, entity: $entity)['lane_key']) {
            throw new LifecycleBlocked('Search lane identity changed.');
        }

        return $this->store->target(scope: $scope, entity: $entity, generation: $gen);
    }

    private function action(IndexTarget $target): CleanupAction
    {
        return new CleanupAction(owner: $this->owner(), namespace: $target->cluster, resourceKind: 'retire-index', resourceId: $target->uid, version: $target->mappingDigest,
            effectKey: 'search.drop.'.hash('sha256', $target->uid.'/'.$target->allocationId.'/'.$target->mappingDigest));
    }

    /** @return array{IndexTarget,IndexDriver&IndexRemovalEvidence} */
    private function verified(DataScope $scope, CleanupAction $action): array
    {
        ReferenceWrites::assertCleanup(scope: $scope, action: $action);
        $target = $this->target(scope: $scope, uid: $action->resourceId);
        $owner = $this->app->make(IndexNamespaceExclusion::class);

        if (get_object_vars($this->action($target)) !== get_object_vars($action)) {
            throw new LifecycleBlocked('Unrecognized search cleanup authority.');
        }
        $lease = ReferenceWrites::cleanupLease(scope: $scope, action: $action, owner: $owner->owner());
        $owner->verify($scope, $lease);
        $owner->verifyTarget($target, $lease);
        $driver = $this->app->make(IndexDriver::class);

        if (! $driver instanceof IndexRemovalEvidence) {
            throw new LifecycleBlocked('Index driver cannot verify physical absence.');
        }

        return [$target, $driver];
    }

    private function journal(IndexTarget $target): Builder
    {
        return $this->store->db($target->scope)->table(LaneStore::SUBMISSIONS)->where([
            'lane_key' => $this->store->laneIdentity(scope: $target->scope, entity: $target->entity)['lane_key'],
            'generation' => $target->generation, 'uid' => $target->uid,
        ]);
    }

    private function submission(IndexTarget $target): ?stdClass
    {
        $rows = $this->journal($target)->where('action', IndexAction::Drop->value)->whereIn('state', ['cleanup-sending', 'cleanup-accepted', 'cleanup-confirmed'])->limit(2)->get();

        if ($rows->count() > 1) {
            throw new LifecycleBlocked('Conflicting index retirement receipts.');
        }

        return $rows->first();
    }

    public function lookup(DataScope $scope, CleanupAction $action): ?CleanupReceipt
    {
        [$target, $driver] = $this->verified(scope: $scope, action: $action);
        $row = $this->submission($target);

        if ($row === null || $row->state !== 'cleanup-confirmed') {
            return null;
        }
        $work = Submission::restore(target: $target, row: $row);

        if (! $driver->absent($target)) {
            throw new LifecycleBlocked('Removed index reappeared.');
        }

        return new CleanupReceipt(action: $action, evidence: json_encode(['uid' => $target->uid, 'allocation' => $target->allocationId,
            'submission' => $work->batch->submissionId, 'task' => $row->task_ref, 'absence' => 'verified'], JSON_THROW_ON_ERROR));
    }

    public function cleanup(DataScope $scope, CleanupAction $action): CleanupReceipt
    {
        [$target, $driver] = $this->verified(scope: $scope, action: $action);
        $db = $this->store->db($scope);

        if ($db->transactionLevel() !== 0) {
            throw new LifecycleBlocked('Index retirement requires committed owner checkpoints.');
        }
        $send = false;
        $row = $db->transaction(function () use ($db, $scope, $target, &$send): stdClass {
            $lane = $this->store->lane(db: $db, scope: $scope, entity: $target->entity);
            $generation = LaneStore::generation(db: $db, lane: $lane, number: $target->generation);
            $gen = (clone $generation)->lockForUpdate()->firstOrFail();

            if (! in_array($gen->state, ['retired', 'removed', 'cleanup'], true) || $lane->active_generation === $gen->generation
                || $lane->writer_generation === $gen->generation || $this->journal($target)->whereIn('state', ['planned', 'sending', 'accepted', 'unknown'])->exists()) {
                throw new LifecycleBlocked('Search generation is still serving or has unresolved submissions.');
            }
            $prior = $this->submission($target);

            if ($prior !== null) {
                return $prior;
            }
            $id = (string) Str::uuid7();
            $batch = new IndexBatch(target: $target, submissionId: $id, action: IndexAction::Drop, documents: []);
            $epoch = Value::integer($gen->claim_epoch) + 1;
            $db->table('corex_search_submissions')->insert(['lane_key' => $lane->lane_key, 'generation' => $target->generation, 'submission_id' => $id,
                'claim_epoch' => $epoch, 'uid' => $target->uid, 'deployment_id' => $scope->deploymentId, 'binding_id' => $scope->bindingId,
                'binding_generation' => $scope->bindingGeneration, 'restore_epoch' => $scope->restoreEpoch, 'action' => IndexAction::Drop->value,
                'state' => 'cleanup-sending', 'batch_digest' => Submission::digest($batch), 'documents' => '[]']);
            LaneStore::one($generation->update(['state' => 'cleanup', 'claim_epoch' => $epoch]));
            $send = $gen->state === 'retired';

            return $this->journal($target)->where('submission_id', $id)->firstOrFail();
        });
        $work = Submission::restore(target: $target, row: $row);
        $journal = $this->journal($target)->where('submission_id', $work->batch->submissionId);

        if (! $driver->absent($target) && $send) {
            $receipt = $driver->submit($work->batch);
            LaneStore::one((clone $journal)->where('state', 'cleanup-sending')->update(['state' => 'cleanup-accepted', 'task_ref' => $receipt->taskId]));
        }
        $row = (clone $journal)->firstOrFail();

        if (($row->task_ref !== null && $driver->lookup($work->batch, new IndexReceipt(Value::text($row->task_ref))) !== IndexOutcome::Confirmed)
            || ! $driver->absent($target)) {
            throw new LifecycleBlocked('Await exact index drop evidence.');
        }
        $this->verified(scope: $scope, action: $action);
        $db->transaction(function () use ($db, $scope, $target, $journal): void {
            $lane = $this->store->lane(db: $db, scope: $scope, entity: $target->entity);
            LaneStore::one(LaneStore::generation(db: $db, lane: $lane, number: $target->generation)->whereIn('state', ['removed', 'cleanup'])->update(['state' => 'removed']));
            LaneStore::one($journal->update(['state' => 'cleanup-confirmed']));
        });

        return $this->lookup(scope: $scope, action: $action) ?? throw new LifecycleBlocked('Index retirement receipt unavailable.');
    }
}
