<?php

declare(strict_types=1);

use CoreX\Search\Internal\Index\DurableIndexLane;
use CoreX\Search\Internal\Index\DurableIndexRebuild;
use CoreX\Search\Tests\PgTestCase;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

beforeEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS corex_search_dirty');
    DB::statement('DROP TABLE IF EXISTS corex_search_rebuilds');
    DB::statement('DROP TABLE IF EXISTS corex_search_lanes');
    DB::statement('CREATE TABLE corex_search_lanes (lane_key varchar(128) primary key, generation varchar(128) not null, committed_revision bigint not null default 0, restore_epoch varchar(64) not null, state varchar(24) not null default \'ready\', claim_epoch bigint not null default 0, active_task varchar(255) null, request_hash varchar(64) null, created_at timestamptz null, updated_at timestamptz null)');
    DB::statement('CREATE TABLE corex_search_dirty (sequence bigserial primary key, lane_key varchar(128) not null, record_id varchar(128) not null, revision bigint not null, tombstone boolean not null default false, restore_epoch varchar(64) not null, submitted_at timestamptz null, completed_at timestamptz null, unique(lane_key, record_id, revision))');
    DB::statement('CREATE TABLE corex_search_rebuilds (rebuild_id varchar(128) primary key, lane_key varchar(128) not null, from_generation varchar(128) not null, to_generation varchar(128) not null, restore_epoch varchar(64) not null, schema_hash varchar(64) not null, fence_revision bigint not null, state varchar(24) not null, swap_task varchar(255) null, marker_hash varchar(64) null, swapped_at timestamptz null, created_at timestamptz null, updated_at timestamptz null, unique(lane_key, to_generation))');
    DB::table('corex_search_lanes')->insert(['lane_key' => 'equipment', 'generation' => 'gen-a', 'restore_epoch' => str_repeat('a', 64), 'state' => 'ready']);
});

it('deduplicates a revision and fences generation and restore epoch before submit', function (): void {
    $lane = new DurableIndexLane(connection: DB::connection(), clock: new SystemClock);
    $lane->recordDirty(laneKey: 'equipment', recordId: 'pump-1', revision: 1, tombstone: false, restoreEpoch: str_repeat('a', 64));
    $lane->recordDirty(laneKey: 'equipment', recordId: 'pump-1', revision: 1, tombstone: false, restoreEpoch: str_repeat('a', 64));
    $lane->recordDirty(laneKey: 'equipment', recordId: 'pump-1', revision: 3, tombstone: false, restoreEpoch: str_repeat('a', 64));
    $lane->recordDirty(laneKey: 'equipment', recordId: 'pump-1', revision: 2, tombstone: true, restoreEpoch: str_repeat('a', 64));

    expect($lane->claim(laneKey: 'equipment', generation: 'gen-b', restoreEpoch: str_repeat('a', 64), limit: 10))->toBeNull()
        ->and($lane->claim(laneKey: 'equipment', generation: 'gen-a', restoreEpoch: str_repeat('b', 64), limit: 10))->toBeNull();

    $claimed = $lane->claim(laneKey: 'equipment', generation: 'gen-a', restoreEpoch: str_repeat('a', 64), limit: 10);

    expect($claimed)->not->toBeNull()
        ->and($claimed->records)->toHaveCount(1)
        ->and($claimed->records[0]['record_id'])->toBe('pump-1')
        ->and($claimed->records[0]['revision'])->toBe(3)
        ->and($lane->claim(laneKey: 'equipment', generation: 'gen-a', restoreEpoch: str_repeat('a', 64), limit: 10))->toBeNull();

    $lane->markSubmitted(claim: $claimed, taskId: 'task-1', requestHash: str_repeat('c', 64));
    $lane->settle(claim: $claimed, outcome: 'unknown');

    expect(DB::table('corex_search_lanes')->value('state'))->toBe('quarantined')
        ->and($lane->claim(laneKey: 'equipment', generation: 'gen-a', restoreEpoch: str_repeat('a', 64), limit: 10))->toBeNull();
})->group('pg');

it('keeps the dirty receipt transactional so a later sweep heals a missed kick', function (): void {
    $lane = new DurableIndexLane(connection: DB::connection(), clock: new SystemClock);

    try {
        DB::transaction(function () use ($lane): void {
            $lane->recordDirty(laneKey: 'equipment', recordId: 'pump-rollback', revision: 1, tombstone: false, restoreEpoch: str_repeat('a', 64));

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // The enclosing source transaction intentionally rolls its dirty receipt back.
    }

    $lane->recordDirty(laneKey: 'equipment', recordId: 'pump-sweep', revision: 2, tombstone: false, restoreEpoch: str_repeat('a', 64));
    $claim = $lane->claim(laneKey: 'equipment', generation: 'gen-a', restoreEpoch: str_repeat('a', 64), limit: 10);

    expect(DB::table('corex_search_dirty')->where('record_id', 'pump-rollback')->exists())->toBeFalse()
        ->and($claim)->not->toBeNull()
        ->and($claim->records[0]['record_id'])->toBe('pump-sweep');
})->group('pg');

it('fences, drains, and quarantines an unknown swap without a blind replay', function (): void {
    $rebuild = new DurableIndexRebuild(connection: DB::connection(), clock: new SystemClock);
    $rebuild->begin(
        rebuildId: 'rebuild-1', laneKey: 'equipment', fromGeneration: 'gen-a', toGeneration: 'gen-b',
        restoreEpoch: str_repeat('a', 64), schemaHash: str_repeat('b', 64),
    );
    $rebuild->fence(rebuildId: 'rebuild-1', watermark: 3);
    $rebuild->markSwapSubmitted(rebuildId: 'rebuild-1', taskId: 'swap-1', markerHash: str_repeat('c', 64));
    $rebuild->settleSwap(rebuildId: 'rebuild-1', outcome: 'unknown');

    expect(DB::table('corex_search_rebuilds')->value('state'))->toBe('unknown')
        ->and(DB::table('corex_search_lanes')->value('state'))->toBe('quarantined')
        ->and(fn (): mixed => $rebuild->markSwapSubmitted(rebuildId: 'rebuild-1', taskId: 'swap-2', markerHash: str_repeat('d', 64)))->toThrow(InvalidArgumentException::class);
})->group('pg');
