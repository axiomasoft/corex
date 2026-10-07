<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Search\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class DurableIndexRebuild
{
    public function __construct(private ConnectionInterface $connection, private readonly ClockInterface $clock) {}

    public function begin(string $rebuildId, string $laneKey, string $fromGeneration, string $toGeneration, string $restoreEpoch, string $schemaHash): void
    {
        $this->assertIdentifier($rebuildId, 'Rebuild identifier');
        $this->assertIdentifier($laneKey, 'Lane key');
        $this->assertIdentifier($fromGeneration, 'Source generation');
        $this->assertIdentifier($toGeneration, 'Target generation');
        $this->assertHash($restoreEpoch, 'Restore epoch');
        $this->assertHash($schemaHash, 'Schema hash');

        if ($fromGeneration === $toGeneration) {
            throw new InvalidArgumentException('A rebuild must target a new generation.');
        }

        $this->connection->transaction(function () use ($rebuildId, $laneKey, $fromGeneration, $toGeneration, $restoreEpoch, $schemaHash): void {
            $lane = $this->connection->table('corex_search_lanes')->where('lane_key', $laneKey)->lockForUpdate()->first();

            if ($lane === null || $lane->generation !== $fromGeneration || $lane->restore_epoch !== $restoreEpoch || $lane->state !== 'ready') {
                throw new InvalidArgumentException('The lane is not eligible for a rebuild.');
            }

            $this->connection->table('corex_search_rebuilds')->insert([
                'rebuild_id' => $rebuildId,
                'lane_key' => $laneKey,
                'from_generation' => $fromGeneration,
                'to_generation' => $toGeneration,
                'restore_epoch' => $restoreEpoch,
                'schema_hash' => $schemaHash,
                'fence_revision' => Value::integer($lane->committed_revision),
                'state' => 'capturing',
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        });
    }

    public function fence(string $rebuildId, int $watermark): void
    {
        if ($watermark < 0) {
            throw new InvalidArgumentException('Rebuild watermark is invalid.');
        }

        $this->connection->transaction(function () use ($rebuildId, $watermark): void {
            $rebuild = $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->lockForUpdate()->first();

            if ($rebuild === null || ! in_array($rebuild->state, ['capturing', 'catching-up'], true)) {
                throw new InvalidArgumentException('The rebuild cannot be fenced.');
            }

            $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->update([
                'fence_revision' => max(Value::integer($rebuild->fence_revision), $watermark),
                'state' => 'fenced',
                'updated_at' => $this->clock->now(),
            ]);
        });
    }

    public function markSwapSubmitted(string $rebuildId, string $taskId, string $markerHash): void
    {
        if ($taskId === '') {
            throw new InvalidArgumentException('Swap task identifier is invalid.');
        }

        $this->assertHash($markerHash, 'Swap marker hash');

        $this->connection->transaction(function () use ($rebuildId, $taskId, $markerHash): void {
            $rebuild = $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->lockForUpdate()->first();

            if ($rebuild === null || $rebuild->state !== 'fenced') {
                throw new InvalidArgumentException('The rebuild is not ready to swap.');
            }

            $pending = $this->connection->table('corex_search_dirty')->where('lane_key', $rebuild->lane_key)->whereNull('completed_at')->exists();

            if ($pending) {
                throw new InvalidArgumentException('A rebuild cannot swap before its durable lane drains.');
            }

            $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->update([
                'state' => 'swapping', 'swap_task' => $taskId, 'marker_hash' => $markerHash, 'updated_at' => $this->clock->now(),
            ]);
        });
    }

    public function settleSwap(string $rebuildId, string $outcome): void
    {
        if (! in_array($outcome, ['succeeded', 'failed', 'canceled', 'unknown'], true)) {
            throw new InvalidArgumentException('Swap outcome is invalid.');
        }

        $this->connection->transaction(function () use ($rebuildId, $outcome): void {
            $rebuild = $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->lockForUpdate()->first();

            if ($rebuild === null || $rebuild->state !== 'swapping') {
                throw new InvalidArgumentException('The swap is no longer current.');
            }

            $lane = $this->connection->table('corex_search_lanes')->where('lane_key', $rebuild->lane_key)->lockForUpdate()->first();

            if ($lane === null || $lane->generation !== $rebuild->from_generation || $lane->restore_epoch !== $rebuild->restore_epoch) {
                throw new InvalidArgumentException('The swap lane is no longer current.');
            }

            if ($outcome === 'succeeded') {
                $this->connection->table('corex_search_lanes')->where('lane_key', $rebuild->lane_key)->update([
                    'generation' => $rebuild->to_generation, 'state' => 'ready', 'updated_at' => $this->clock->now(),
                ]);
                $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->update([
                    'state' => 'verified', 'swapped_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
                ]);

                return;
            }

            $this->connection->table('corex_search_rebuilds')->where('rebuild_id', $rebuildId)->update([
                'state' => $outcome === 'unknown' ? 'unknown' : 'failed', 'updated_at' => $this->clock->now(),
            ]);
            $this->connection->table('corex_search_lanes')->where('lane_key', $rebuild->lane_key)->update([
                'state' => $outcome === 'unknown' ? 'quarantined' : 'ready', 'updated_at' => $this->clock->now(),
            ]);
        });
    }

    private function assertIdentifier(string $value, string $name): void
    {
        if (preg_match('/\A[a-zA-Z0-9._-]{1,128}\z/D', $value) !== 1) {
            throw new InvalidArgumentException($name.' is invalid.');
        }
    }

    private function assertHash(string $value, string $name): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new InvalidArgumentException($name.' is invalid.');
        }
    }
}
