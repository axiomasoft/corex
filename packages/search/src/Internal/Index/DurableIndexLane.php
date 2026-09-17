<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Search\Support\IndexClaim;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final readonly class DurableIndexLane
{
    public function __construct(private ConnectionInterface $connection) {}

    public function recordDirty(string $laneKey, string $recordId, int $revision, bool $tombstone, string $restoreEpoch): void
    {
        $this->assertLaneKey($laneKey);

        if ($recordId === '' || $revision < 1 || preg_match('/\A[a-f0-9]{64}\z/D', $restoreEpoch) !== 1) {
            throw new InvalidArgumentException('Index dirty record exceeds its bounded lifecycle contract.');
        }

        $this->connection->transaction(function () use ($laneKey, $recordId, $revision, $tombstone, $restoreEpoch): void {
            $latest = $this->connection->table('corex_search_dirty')
                ->where('lane_key', $laneKey)
                ->where('record_id', $recordId)
                ->where('restore_epoch', $restoreEpoch)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->value('revision');

            if ($latest !== null && (int) $latest >= $revision) {
                return;
            }

            $this->connection->table('corex_search_dirty')->insertOrIgnore([
                'lane_key' => $laneKey,
                'record_id' => $recordId,
                'revision' => $revision,
                'tombstone' => $tombstone,
                'restore_epoch' => $restoreEpoch,
            ]);
        });
    }

    public function claim(string $laneKey, string $generation, string $restoreEpoch, int $limit): ?IndexClaim
    {
        $this->assertLaneKey($laneKey);

        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Index claim limit is out of bounds.');
        }

        return $this->connection->transaction(function () use ($laneKey, $generation, $restoreEpoch, $limit): ?IndexClaim {
            $lane = $this->connection->table('corex_search_lanes')->where('lane_key', $laneKey)->lockForUpdate()->first();

            if ($lane === null || $lane->generation !== $generation || $lane->restore_epoch !== $restoreEpoch || $lane->state !== 'ready') {
                return null;
            }

            $rows = $this->connection->table('corex_search_dirty')
                ->where('lane_key', $laneKey)->where('restore_epoch', $restoreEpoch)->whereNull('submitted_at')
                ->orderBy('sequence')->limit($limit)->lockForUpdate()->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $latestRows = $rows->groupBy('record_id')->map(static fn ($records): object => $records->sortByDesc('revision')->first());
            $superseded = $rows->filter(static fn (object $row): bool => $latestRows->get($row->record_id)->sequence !== $row->sequence);

            if ($superseded->isNotEmpty()) {
                $this->connection->table('corex_search_dirty')->whereIn('sequence', $superseded->pluck('sequence')->all())->update(['completed_at' => now()]);
            }

            $claimEpoch = ((int) $lane->claim_epoch) + 1;
            $this->connection->table('corex_search_lanes')->where('lane_key', $laneKey)->update([
                'state' => 'claimed',
                'claim_epoch' => $claimEpoch,
                'updated_at' => now(),
            ]);

            return new IndexClaim(
                laneKey: $laneKey,
                generation: $generation,
                restoreEpoch: $restoreEpoch,
                claimEpoch: $claimEpoch,
                records: $latestRows->values()->map(static fn (object $row): array => (array) $row)->all(),
            );
        });
    }

    public function markSubmitted(IndexClaim $claim, string $taskId, string $requestHash): void
    {
        if ($taskId === '' || preg_match('/\A[a-f0-9]{64}\z/D', $requestHash) !== 1) {
            throw new InvalidArgumentException('Index submission receipt is invalid.');
        }

        $this->connection->transaction(function () use ($claim, $taskId, $requestHash): void {
            $lane = $this->lockedLane($claim);

            if ($lane === null || $lane->state !== 'claimed') {
                throw new InvalidArgumentException('Index claim is no longer current.');
            }

            $sequences = array_column($claim->records, 'sequence');
            $this->connection->table('corex_search_dirty')->whereIn('sequence', $sequences)->whereNull('submitted_at')->update(['submitted_at' => now()]);
            $this->connection->table('corex_search_lanes')->where('lane_key', $claim->laneKey)->update([
                'state' => 'submitted', 'active_task' => $taskId, 'request_hash' => $requestHash, 'updated_at' => now(),
            ]);
        });
    }

    public function settle(IndexClaim $claim, string $outcome): void
    {
        if (! in_array($outcome, ['succeeded', 'failed', 'canceled', 'unknown'], true)) {
            throw new InvalidArgumentException('Index outcome is invalid.');
        }

        $this->connection->transaction(function () use ($claim, $outcome): void {
            $lane = $this->lockedLane($claim);

            if ($lane === null || $lane->state !== 'submitted') {
                throw new InvalidArgumentException('Index submission is no longer current.');
            }

            if ($outcome === 'unknown') {
                $this->connection->table('corex_search_lanes')->where('lane_key', $claim->laneKey)->update([
                    'state' => 'quarantined', 'updated_at' => now(),
                ]);

                return;
            }

            $sequences = array_column($claim->records, 'sequence');

            if ($outcome === 'succeeded') {
                $maxRevision = max(array_map(static fn (array $record): int => (int) $record['revision'], $claim->records));
                $this->connection->table('corex_search_dirty')->whereIn('sequence', $sequences)->update(['completed_at' => now()]);
                $this->connection->table('corex_search_lanes')->where('lane_key', $claim->laneKey)->update([
                    'state' => 'ready', 'active_task' => null, 'request_hash' => null,
                    'committed_revision' => max((int) $lane->committed_revision, $maxRevision), 'updated_at' => now(),
                ]);

                return;
            }

            $this->connection->table('corex_search_dirty')->whereIn('sequence', $sequences)->update(['submitted_at' => null]);
            $this->connection->table('corex_search_lanes')->where('lane_key', $claim->laneKey)->update([
                'state' => 'ready', 'active_task' => null, 'request_hash' => null, 'updated_at' => now(),
            ]);
        });
    }

    private function lockedLane(IndexClaim $claim): ?object
    {
        $lane = $this->connection->table('corex_search_lanes')->where('lane_key', $claim->laneKey)->lockForUpdate()->first();

        if ($lane === null || $lane->generation !== $claim->generation || $lane->restore_epoch !== $claim->restoreEpoch || (int) $lane->claim_epoch !== $claim->claimEpoch) {
            return null;
        }

        return $lane;
    }

    private function assertLaneKey(string $laneKey): void
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,127}\z/D', $laneKey) !== 1) {
            throw new InvalidArgumentException('Index lane key is invalid.');
        }
    }
}
