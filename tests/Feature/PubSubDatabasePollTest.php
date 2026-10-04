<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\WorkerRuntime;
use CoreX\Messaging\Internal\DatabasePollPubSub;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

const PUBSUB_DB_POLL_TEST_CONNECTION = 'pgsql';

function pubsubPg(): Connection
{
    return DB::connection(PUBSUB_DB_POLL_TEST_CONNECTION);
}

/** Fakes a console worker (not Octane) — the only runtime the guard allows by default (P1.24). */
final class WorkerConsoleRuntime implements WorkerRuntime
{
    public function isOctane(): bool
    {
        return false;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    // D12: sys_pubsub_messages/sys_pubsub_cursors are PG-only. Clean schema per
    // test (mirrors FeatureFlagsTest's/DepartmentTest's pg-lane pattern).
    pubsubPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    pubsubPg()->statement('CREATE SCHEMA public');
    DB::purge(PUBSUB_DB_POLL_TEST_CONNECTION);

    $this->artisan('migrate', [
        '--database' => PUBSUB_DB_POLL_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

function pollingPubSub(): DatabasePollPubSub
{
    return app()->make(DatabasePollPubSub::class, [
        'connection' => PUBSUB_DB_POLL_TEST_CONNECTION,
        'runtime' => new WorkerConsoleRuntime,
    ]);
}

it('AC-25(a)/(б) publish→subscribe delivers the payload via the poll cursor, from a console worker context', function (): void {
    $pubsub = pollingPubSub();

    $pubsub->publish('module.enabled', ['module' => 'crm']);
    $pubsub->publish('module.enabled', ['module' => 'tasks']);
    $pubsub->publish('other.channel', ['module' => 'ignored']);

    $received = [];
    // A brand-new subscriber bootstraps its cursor at the channel's current
    // max(id) (skips pre-existing backlog) — pass replayBacklog to see it.
    $pubsub->subscribe('module.enabled', function (array $payload) use (&$received): void {
        $received[] = $payload;
    }, maxPolls: 1, replayBacklog: true);

    expect($received)->toBe([
        ['module' => 'crm'],
        ['module' => 'tasks'],
    ]);
});

it('persists rows in the configured table with a bigint cursor', function (): void {
    $pubsub = pollingPubSub();
    $pubsub->publish('module.enabled', ['module' => 'crm']);

    $row = pubsubPg()->table(app(CoreOptions::class)->pubsubMessagesTable)->first();

    expect($row->channel)->toBe('module.enabled')
        ->and(json_decode((string) $row->payload, true))->toBe(['module' => 'crm'])
        ->and($row->id)->toBeInt();
});

it('(г) D44: a new subscriber does NOT replay pre-existing history by default (skips straight to max(id))', function (): void {
    $pubsub = pollingPubSub();
    $pubsub->publish('module.enabled', ['module' => 'crm']);
    $pubsub->publish('module.enabled', ['module' => 'tasks']);
    $pubsub->publish('module.enabled', ['module' => 'sales']);

    $received = [];
    $pubsub->subscribe('module.enabled', function (array $payload) use (&$received): void {
        $received[] = $payload;
    }, maxPolls: 1, subscriber: 'invalidation-worker');

    expect($received)->toBe([]);
});

it('(г) D44: a rolling restart of the SAME subscriber resumes from its persisted cursor, not from 0 or from a fresh max(id)', function (): void {
    $pubsub = pollingPubSub();

    $pubsub->publish('module.enabled', ['module' => 'crm']);
    $pubsub->publish('module.enabled', ['module' => 'tasks']);

    // First run: bootstraps the cursor at max(id) — no history is replayed.
    $firstRun = [];
    $pubsub->subscribe('module.enabled', function (array $payload) use (&$firstRun): void {
        $firstRun[] = $payload;
    }, maxPolls: 1, subscriber: 'invalidation-worker');
    expect($firstRun)->toBe([]);

    // A message is published during the "restart window" (the process is down).
    $pubsub->publish('module.enabled', ['module' => 'sales']);

    // Second run (the restarted process): must see the restart-window message —
    // NOT skip it (max(id) start on every subscribe(), the rejected D44
    // design) and NOT replay 'crm'/'tasks' again (cursor-at-0 fallback).
    $secondRun = [];
    $pubsub->subscribe('module.enabled', function (array $payload) use (&$secondRun): void {
        $secondRun[] = $payload;
    }, maxPolls: 1, subscriber: 'invalidation-worker');

    expect($secondRun)->toBe([['module' => 'sales']]);
});

it('(д) corex:messaging:prune-pubsub truncates rows older than the threshold, leaving fresh rows intact', function (): void {
    pubsubPg()->table(app(CoreOptions::class)->pubsubMessagesTable)->insert([
        ['channel' => 'module.enabled', 'payload' => '{"module":"stale"}', 'created_at' => now()->subDays(60)],
        ['channel' => 'module.enabled', 'payload' => '{"module":"fresh"}', 'created_at' => now()],
    ]);

    $this->artisan('corex:messaging:prune-pubsub', [
        '--older-than' => '30 days',
        '--database' => PUBSUB_DB_POLL_TEST_CONNECTION,
    ])->assertSuccessful();

    $remaining = pubsubPg()->table(app(CoreOptions::class)->pubsubMessagesTable)->pluck('payload');

    expect($remaining)->toHaveCount(1)
        ->and(json_decode((string) $remaining->first(), true))->toBe(['module' => 'fresh']);
});
