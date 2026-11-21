<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use Closure;
use CoreX\Context\Data\OperationKey;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\Value;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\GenerationRef;
use CoreX\Search\Rebuild\Data\RebuildInput;
use CoreX\Search\Rebuild\Data\RebuildPlan;
use CoreX\Search\Rebuild\Data\RebuildPlanRef;
use CoreX\Search\Rebuild\Data\RebuildRef;
use CoreX\Search\Rebuild\Data\RebuildStatus;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;

#[Internal]
final readonly class RebuildJournal implements RebuildWorker, SearchRebuilds
{
    /**
     * @param  Closure():IndexProjection  $projection
     * @param  Closure():IndexDriver  $driver
     */
    public function __construct(private LaneStore $store, private Closure $projection, private Closure $driver, private ClockInterface $clock, private int $retentionSeconds) {}

    public function plan(DataScope $scope, RebuildInput $input): RebuildPlan
    {
        $db = $this->store->db($scope, true);

        return $db->transaction(function () use ($db, $scope, $input): RebuildPlan {
            $lane = $this->store->lane($db, $scope, $input->entity, true);
            [$mapping, $policy] = ($this->projection)()->witnesses($scope, $input->entity);
            $id = (string) Str::uuid7();
            $db->table('corex_search_rebuilds')->insert(['rebuild_id' => $id, 'lane_key' => $lane->lane_key, 'state' => 'planned',
                'mapping_digest' => $mapping, 'policy_digest' => $policy, 'from_generation' => $lane->active_generation]);

            return new RebuildPlan(new RebuildPlanRef($id), $mapping, self::nullableInt($lane->active_generation));
        });
    }

    public function start(DataScope $scope, RebuildPlanRef $plan, OperationKey $key): RebuildRef
    {
        $db = $this->store->db($scope, true);

        return $db->transaction(function () use ($db, $scope, $plan, $key): RebuildRef {
            $lane = $this->store->refLane($db, $scope, $plan->id);
            $run = self::run($db, $lane, $plan->id)->lockForUpdate()->firstOrFail();
            $ref = new RebuildRef($plan->id);

            if ($run->operation_key === $key->id) {
                return $ref;
            }

            if ($run->state !== 'planned' || $lane->rebuild_id !== null || $lane->active_generation !== $run->from_generation) {
                throw new LogicException('Search rebuild plan conflicts with the current generation.');
            }
            $entity = new EntityHandle(Value::text($lane->entity));
            $this->enlist($db, $scope, $lane);
            $this->checkWitnesses($scope, $entity, $run);
            $dest = Value::integer($lane->last_generation) + 1;
            $allocation = (string) Str::uuid7();
            $uid = IndexTarget::uidFor($scope, $entity, $this->store->backend, $this->store->cluster, $dest, $allocation);
            $db->table('corex_search_generations')->insert(['lane_key' => $lane->lane_key, 'generation' => $dest, 'cluster' => $this->store->cluster,
                'uid' => $uid, 'allocation_id' => $allocation, 'mapping_digest' => $run->mapping_digest, 'policy_digest' => $run->policy_digest, 'state' => 'allocated']);
            LaneStore::one(self::run($db, $lane, $ref->id)->where('state', 'planned')->update(['operation_key' => $key->id,
                'state' => 'scanning', 'to_generation' => $dest, 'start_seq' => $lane->last_allocated_seq]));
            LaneStore::one($db->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->whereNull('rebuild_id')
                ->where('last_generation', $lane->last_generation)->update(['rebuild_id' => $ref->id, 'last_generation' => $dest]));

            return $ref;
        });
    }

    public function inspect(DataScope $scope, RebuildRef $ref): RebuildStatus
    {
        $db = $this->store->db($scope, true);
        $lanes = $db->table(LaneStore::LANES)->select('lane_key')->where($this->store->identity($scope));
        $row = $db->table(LaneStore::RUNS)->useWritePdo()->whereIn('lane_key', $lanes)
            ->whereNull('source_revision')->where('rebuild_id', $ref->id)->firstOrFail();

        return $this->status($row);
    }

    public function barrier(DataScope $scope, RebuildRef $ref): RebuildStatus
    {
        $db = $this->store->db($scope, true);

        return $db->transaction(function () use ($db, $scope, $ref): RebuildStatus {
            $lane = $this->store->refLane($db, $scope, $ref->id);
            $run = self::run($db, $lane, $ref->id)->lockForUpdate()->firstOrFail();

            if (in_array($run->state, ['barrier', 'published'], true)) {
                return $this->status($run);
            }

            if ($lane->rebuild_id !== $ref->id || $run->state !== 'catching-up' || $lane->active_generation !== $run->from_generation) {
                throw new LogicException('Search destination scan has not completed.');
            }
            $this->enlist($db, $scope, $lane);
            $this->checkWitnesses($scope, new EntityHandle(Value::text($lane->entity)), $run);
            LaneStore::one(self::run($db, $lane, $ref->id)->where('state', 'catching-up')->update(['state' => 'barrier', 'barrier_seq' => $lane->last_allocated_seq]));
            LaneStore::one($db->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->where('rebuild_id', $ref->id)
                ->whereNull('freeze_seq')->update(['freeze_seq' => $lane->last_allocated_seq]));

            return $this->status(self::run($db, $lane, $ref->id)->firstOrFail());
        });
    }

    public function publish(DataScope $scope, RebuildRef $ref): RebuildStatus
    {
        $db = $this->store->db($scope, true);

        if ($db->transactionLevel() !== 0) {
            throw new LogicException('Search publication requires its own commit.');
        }
        $status = $this->inspect($scope, $ref);

        if ($status->state === 'published') {
            return $status;
        }
        $lane = $db->table(LaneStore::LANES)->useWritePdo()->where($this->store->identity($scope))->whereIn('lane_key',
            $db->table(LaneStore::RUNS)->select('lane_key')->where('rebuild_id', $ref->id))->firstOrFail();
        $gen = LaneStore::generation($db, $lane, $status->destination)->useWritePdo()->firstOrFail();
        $entity = new EntityHandle(Value::text($lane->entity));

        if ($status->state !== 'barrier' || $gen->state !== 'catching-up' || $gen->confirmed_seq !== $lane->freeze_seq
            || ! ($this->driver)()->canary($this->store->target($scope, $entity, $gen))) {
            $current = $this->inspect($scope, $ref);

            if ($current->state === 'published') {
                return $current;
            }

            throw new LogicException('Search publication barrier is not ready.');
        }

        return $db->transaction(function () use ($db, $scope, $ref, $status, $gen, $entity): RebuildStatus {
            $lane = $this->store->refLane($db, $scope, $ref->id);
            $run = self::run($db, $lane, $ref->id)->lockForUpdate()->firstOrFail();

            if ($run->state === 'published') {
                return $this->status($run);
            }

            if ($lane->rebuild_id !== $ref->id || $run->state !== 'barrier' || $lane->active_generation !== $run->from_generation
                || $run->barrier_seq === null || $run->barrier_seq !== $lane->freeze_seq) {
                throw new LogicException('Search publication claim lost.');
            }
            $this->enlist($db, $scope, $lane);
            $this->checkWitnesses($scope, $entity, $run);
            $dest = LaneStore::generation($db, $lane, $status->destination);

            if ($db->table(LaneStore::SUBMISSIONS)->where('lane_key', $lane->lane_key)->where('generation', $status->destination)
                ->whereIn('state', ['planned', 'sending', 'accepted', 'unknown'])->exists()) {
                throw new LogicException('Search destination has unresolved submissions.');
            }

            if ($run->from_generation !== null) {
                $previous = LaneStore::generation($db, $lane, Value::integer($run->from_generation))->firstOrFail();

                if ($previous->state === 'active') {
                    LaneStore::one(LaneStore::generation($db, $lane, Value::integer($run->from_generation))->where('state', 'active')->update(['state' => 'retained', 'retain_until' => $this->clock->now()->modify('+'.$this->retentionSeconds.' seconds')->format('Y-m-d H:i:s.uP')]));
                } elseif (! in_array($previous->state, ['quarantined', 'failed'], true)) {
                    throw new LogicException('Search source generation is no longer current.');
                }
            }
            LaneStore::one($dest->where('state', 'catching-up')->where('confirmed_seq', $run->barrier_seq)->where('uid', $gen->uid)
                ->where('mapping_digest', $run->mapping_digest)->where('claim_epoch', $gen->claim_epoch)->update(['state' => 'active', 'retain_until' => null]));
            LaneStore::one($db->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->where('rebuild_id', $ref->id)
                ->where('active_generation', $run->from_generation)->where('freeze_seq', $run->barrier_seq)->update([
                    'active_generation' => $status->destination, 'writer_generation' => $status->destination,
                    'next_seq' => Value::integer($run->barrier_seq) + 1, 'rebuild_id' => null, 'freeze_seq' => null]));
            LaneStore::one(self::run($db, $lane, $ref->id)->where('state', 'barrier')->update(['state' => 'published']));
            $requests = $db->table('corex_search_rebuilds')->where('lane_key', $lane->lane_key)->where('state', 'requested');
            (clone $requests)->where('mapping_digest', $run->mapping_digest)->update(['state' => 'satisfied']);
            $requests->where('mapping_digest', '<>', $run->mapping_digest)->update(['state' => 'superseded']);

            return $this->status(self::run($db, $lane, $ref->id)->firstOrFail());
        });
    }

    public function cancel(DataScope $scope, RebuildRef $ref): RebuildStatus
    {
        $db = $this->store->db($scope, true);

        return $db->transaction(function () use ($db, $scope, $ref): RebuildStatus {
            $lane = $this->store->refLane($db, $scope, $ref->id);
            $run = self::run($db, $lane, $ref->id)->lockForUpdate()->firstOrFail();

            if ($run->state === 'cancelled') {
                return $this->status($run);
            }

            if ($lane->rebuild_id !== $ref->id || ! in_array($run->state, ['scanning', 'catching-up', 'barrier'], true)) {
                throw new LogicException('Search rebuild cannot be cancelled.');
            }
            LaneStore::one(LaneStore::generation($db, $lane, Value::integer($run->to_generation))->whereIn('state', ['allocated', 'scanning', 'catching-up'])
                ->update(['state' => $run->rollback_of === null ? 'retired' : 'retained']));
            self::release($db, $lane, $ref->id, 'cancelled');

            return $this->status(self::run($db, $lane, $ref->id)->firstOrFail());
        });
    }

    public static function release(ConnectionInterface $db, stdClass $lane, string $id, string $state): void
    {
        $next = 1;

        if ($lane->active_generation !== null) {
            $old = LaneStore::generation($db, $lane, Value::integer($lane->active_generation))->firstOrFail();
            $next = Value::integer($old->confirmed_seq) + 1;
        }
        LaneStore::one($db->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->where('rebuild_id', $id)
            ->update(['rebuild_id' => null, 'freeze_seq' => null, 'next_seq' => $next]));
        LaneStore::one(self::run($db, $lane, $id)->whereIn('state', ['scanning', 'catching-up', 'barrier'])->update(['state' => $state]));
    }

    public function rollback(DataScope $scope, RebuildRef $ref, GenerationRef $expected): RebuildRef
    {
        $db = $this->store->db($scope, true);

        return $db->transaction(function () use ($db, $scope, $ref, $expected): RebuildRef {
            $lane = $this->store->refLane($db, $scope, $ref->id);
            $run = self::run($db, $lane, $ref->id)->lockForUpdate()->firstOrFail();
            $key = 'rollback/'.$ref->id.'/'.$expected->value;
            $existing = $db->table(LaneStore::RUNS)->where('lane_key', $lane->lane_key)->where('rollback_of', $ref->id)->where('operation_key', $key)->first();

            if ($existing !== null) {
                return new RebuildRef(Value::text($existing->rebuild_id));
            }

            if ($run->state !== 'published' || $run->from_generation === null || $lane->rebuild_id !== null
                || $lane->active_generation !== $run->to_generation || Value::integer($lane->active_generation) !== $expected->value) {
                throw new LogicException('Search rollback conflicts with the active pointer.');
            }
            $this->enlist($db, $scope, $lane);
            $dest = LaneStore::generation($db, $lane, Value::integer($run->from_generation))->where('state', 'retained')->lockForUpdate()->firstOrFail();
            [$mapping, $policy] = ($this->projection)()->witnesses($scope, new EntityHandle(Value::text($lane->entity)));

            if ($dest->mapping_digest !== $mapping || $dest->policy_digest !== $policy || $dest->retain_until === null
                || new DateTimeImmutable(Value::text($dest->retain_until)) <= $this->clock->now()
                || $db->table(LaneStore::DIRTY)->where('lane_key', $lane->lane_key)->where('seq', '>', $dest->confirmed_seq)->count() !== Value::integer($lane->last_allocated_seq) - Value::integer($dest->confirmed_seq)) {
                throw new LogicException('Search rollback requires a new full rebuild.');
            }
            $id = (string) Str::uuid7();
            $db->table('corex_search_rebuilds')->insert(['rebuild_id' => $id, 'operation_key' => $key, 'rollback_of' => $ref->id, 'lane_key' => $lane->lane_key,
                'from_generation' => $lane->active_generation, 'to_generation' => $dest->generation, 'mapping_digest' => $mapping, 'policy_digest' => $policy,
                'state' => 'catching-up', 'start_seq' => $lane->last_allocated_seq]);
            LaneStore::one(LaneStore::generation($db, $lane, Value::integer($dest->generation))->where('state', 'retained')->update(['state' => 'catching-up']));
            LaneStore::one($db->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->whereNull('rebuild_id')->update(['rebuild_id' => $id]));

            return new RebuildRef($id);
        });
    }

    private function enlist(ConnectionInterface $db, DataScope $scope, stdClass $lane): void
    {
        ReferenceWrites::enlist($db, $scope, 'axiomasoft/corex-search', 'search-lane', [
            'entity' => Value::text($lane->entity), 'backend' => $this->store->backend,
        ]);
    }

    private static function run(ConnectionInterface $db, stdClass $lane, string $id): Builder
    {
        return $db->table(LaneStore::RUNS)->where('lane_key', $lane->lane_key)->whereNull('source_revision')->where('rebuild_id', $id);
    }

    private function status(stdClass $run): RebuildStatus
    {
        return new RebuildStatus(new RebuildRef(Value::text($run->rebuild_id)), Value::integer($run->to_generation), self::nullableInt($run->from_generation),
            self::nullableInt($run->barrier_seq), Value::text($run->state));
    }

    private static function nullableInt(mixed $number): ?int
    {
        return $number === null ? null : Value::integer($number);
    }

    private function checkWitnesses(DataScope $scope, EntityHandle $entity, stdClass $run): void
    {
        if (($this->projection)()->witnesses($scope, $entity) !== [$run->mapping_digest, $run->policy_digest]) {
            throw new LogicException('Search mapping or policy witness has changed.');
        }
    }
}
