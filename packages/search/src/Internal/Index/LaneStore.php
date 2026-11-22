<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use LogicException;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class LaneStore implements ActiveGenerations
{
    public const string LANES = 'corex_search_lanes';

    public const string GENERATIONS = 'corex_search_generations';

    public const string DIRTY = 'corex_search_dirty';

    public const string RUNS = 'corex_search_rebuilds';

    public const string SUBMISSIONS = 'corex_search_submissions';

    public function __construct(private DatabaseManager $database, private CurrentWorkScope $current, public string $backend, public string $cluster) {}

    public function db(DataScope $scope, bool $worker = false): ConnectionInterface
    {
        if ($worker && $scope->principalKind !== 'service') {
            throw new LogicException('Search maintenance requires a service principal.');
        }
        $db = $this->database->connection();
        $this->current->assert(scope: $scope, connection: $db);

        return $db;
    }

    /** @return array<string,string|int> */
    public function identity(DataScope $scope): array
    {
        return [
            'deployment_id' => $scope->deploymentId, 'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration, 'restore_epoch' => $scope->restoreEpoch,
            'account_id' => $scope->accountId, 'account_incarnation' => $scope->accountIncarnation,
            'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId,
            'backend' => $this->backend, 'cluster' => $this->cluster,
        ];
    }

    /** @return array<string,string|int> */
    public function laneIdentity(DataScope $scope, EntityHandle $entity): array
    {
        $identity = [...$this->identity($scope), 'entity' => $entity->value];

        return ['lane_key' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)), ...$identity];
    }

    public function lane(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, bool $create = false): stdClass
    {
        $this->current->assert(scope: $scope, connection: $db);
        $identity = $this->laneIdentity(scope: $scope, entity: $entity);

        if ($create) {
            ReferenceWrites::enlist(connection: $db, scope: $scope, producer: 'axiomasoft/corex-search', kind: 'search-lane', witness: ['entity' => $entity->value, 'backend' => $this->backend]);
            $db->table(self::LANES)->insertOrIgnore($identity);
        }

        return $db->table(self::LANES)->where($identity)->lockForUpdate()->firstOrFail();
    }

    public function refLane(ConnectionInterface $db, DataScope $scope, string $ref): stdClass
    {
        $this->current->assert(scope: $scope, connection: $db);

        return $db->table(self::LANES)->where($this->identity($scope))->whereIn('lane_key',
            $db->table(self::RUNS)->select('lane_key')->where('rebuild_id', $ref))->lockForUpdate()->firstOrFail();
    }

    public static function generation(ConnectionInterface $db, stdClass $lane, int $number): Builder
    {
        return $db->table(self::GENERATIONS)->where('lane_key', Value::text($lane->lane_key))->where('generation', $number);
    }

    public function target(DataScope $scope, EntityHandle $entity, stdClass $generation): IndexTarget
    {
        return new IndexTarget(scope: $scope, entity: $entity, backend: $this->backend, cluster: $this->cluster, generation: Value::integer($generation->generation),
            uid: Value::text($generation->uid), mappingDigest: Value::text($generation->mapping_digest), allocationId: Value::text($generation->allocation_id));
    }

    public function active(DataScope $scope, EntityHandle $entity): ?IndexTarget
    {
        $db = $this->db($scope);
        $identity = $this->laneIdentity(scope: $scope, entity: $entity);
        $active = $db->table(self::LANES)->select('active_generation')->where($identity);
        $gen = $db->table(self::GENERATIONS)->useWritePdo()->where('lane_key', $identity['lane_key'])->where('cluster', $this->cluster)
            ->where('state', 'active')->whereIn('generation', $active)->first();

        return $gen === null ? null : $this->target(scope: $scope, entity: $entity, generation: $gen);
    }

    public static function one(int $count): void
    {
        if ($count !== 1) {
            throw new LogicException('Search generation claim lost.');
        }
    }
}
