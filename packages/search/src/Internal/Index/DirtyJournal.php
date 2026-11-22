<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Search\Internal\Value;
use CoreX\Search\Lane\Contracts\SearchInvalidations;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DirtyJournal implements SearchInvalidations
{
    public function __construct(private LaneStore $store) {}

    public function apply(ConnectionInterface $writer, MessageEnvelope $event, EntityHandle $entity, array $records): void
    {
        if ($writer->transactionLevel() < 1 || $this->store->db($event->scope) !== $writer || $records === []) {
            throw new LogicException('Search invalidations require the open subscriber transaction and records.');
        }
        $subjectEntity = $event->subject instanceof RecordRef ? $event->subject->entity : $event->subject->value;

        if ($subjectEntity !== $entity->value) {
            throw new LogicException('Search invalidation subject does not match the entity.');
        }
        $lane = $this->store->lane(db: $writer, scope: $event->scope, entity: $entity, create: true);
        $seq = Value::integer($lane->last_allocated_seq);
        foreach ($records as $record) {
            if ($record->entity !== $entity->value || ($event->subject instanceof RecordRef && $record->id !== $event->subject->id)) {
                throw new LogicException('Search invalidation record does not match its subject.');
            }
            $inserted = $writer->table('corex_search_dirty')->insertOrIgnoreReturning(['lane_key' => $lane->lane_key, 'seq' => $seq + 1,
                'source' => $event->source, 'event_id' => $event->messageId, 'record_id' => $record->id], ['seq'], ['lane_key', 'source', 'event_id', 'record_id']);
            $seq += $inserted->count();
        }
        LaneStore::one($writer->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->where('last_allocated_seq', $lane->last_allocated_seq)->update(['last_allocated_seq' => $seq]));
    }
}
