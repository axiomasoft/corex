<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Search\Internal\Value;
use CoreX\Search\Lane\Contracts\SearchMappingInvalidations;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectionContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MappingJournal implements SearchMappingInvalidations
{
    private const string UNPLANNED_POLICY = '0000000000000000000000000000000000000000000000000000000000000000';

    /** @param Closure():SearchProjectionContributor $projection */
    public function __construct(private LaneStore $store, private Closure $projection) {}

    public function apply(ConnectionInterface $writer, MessageEnvelope $event, EntityHandle $entity, string $sourceRevision, string $sourceMappingDigest): void
    {
        if ($writer->transactionLevel() < 1 || $this->store->db($event->scope) !== $writer
            || ! ($event->subject instanceof EntityHandle) || $event->subject->value !== $entity->value
            || preg_match('/\A[a-f0-9]{64}\z/D', $sourceRevision) !== 1 || preg_match('/\A[a-f0-9]{64}\z/D', $sourceMappingDigest) !== 1) {
            throw new LogicException('Search mapping invalidations require an entity fact and its open subscriber transaction.');
        }

        $lane = $this->store->lane($writer, $event->scope, $entity, true);
        $requests = $writer->table('corex_search_rebuilds')->where('lane_key', $lane->lane_key);
        $key = 'schema-event/'.hash('sha256', json_encode([$event->source, $event->messageId], JSON_THROW_ON_ERROR));

        if ((clone $requests)->where('operation_key', $key)->exists()) {
            return;
        }

        // Derive the required mapping from current owners. Delivery order cannot regress it.
        $mapping = ($this->projection)()->describe(new ProjectionContext($event->scope, $entity))->revision;
        $active = $lane->active_generation === null ? null : LaneStore::generation($writer, $lane, Value::integer($lane->active_generation))
            ->where('state', 'active')->value('mapping_digest');

        $pending = (clone $requests)->where('state', 'requested');
        (clone $pending)->where('mapping_digest', '<>', $mapping)->update(['state' => 'superseded']);
        $state = match (true) {
            $active === $mapping => 'satisfied',
            $pending->where('mapping_digest', $mapping)->exists() => 'coalesced',
            default => 'requested',
        };
        $writer->table('corex_search_rebuilds')->insert([
            'rebuild_id' => (string) Str::uuid7(), 'lane_key' => $lane->lane_key, 'operation_key' => $key,
            'state' => $state, 'from_generation' => $lane->active_generation,
            'mapping_digest' => $mapping, 'policy_digest' => self::UNPLANNED_POLICY,
            'source_revision' => $sourceRevision, 'source_mapping_digest' => $sourceMappingDigest,
        ]);
    }

    public function pending(DataScope $scope, EntityHandle $entity): bool
    {
        $db = $this->store->db($scope, true);
        $identity = $this->store->laneIdentity($scope, $entity);
        $lanes = $db->table(LaneStore::LANES)->select('lane_key')->where($identity);

        return $db->table(LaneStore::RUNS)->useWritePdo()
            ->whereIn('lane_key', $lanes)->where('state', 'requested')->exists();
    }
}
