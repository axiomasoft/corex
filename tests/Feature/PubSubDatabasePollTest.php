<?php

declare(strict_types=1);

use CoreX\Contracts\PubSub;
use CoreX\Enums\PubSubDriver;
use CoreX\PubSub\DatabasePollPubSub;
use CoreX\PubSub\PubSubManager;
use CoreX\Support\Config;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

const PUBSUB_DB_POLL_TEST_CONNECTION = 'pgsql';

function pubsubPg(): Connection
{
    return DB::connection(PUBSUB_DB_POLL_TEST_CONNECTION);
}

beforeEach(function (): void {
    // D12: sys_pubsub_messages is PG-only. Clean schema per test (mirrors
    // FeatureFlagsTest's/DepartmentTest's pg-lane pattern).
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

it('AC-25(a): publish→subscribe delivers the payload via the poll cursor', function (): void {
    $pubsub = new DatabasePollPubSub(connection: PUBSUB_DB_POLL_TEST_CONNECTION);

    $pubsub->publish('module.enabled', ['module' => 'crm']);
    $pubsub->publish('module.enabled', ['module' => 'tasks']);
    $pubsub->publish('other.channel', ['module' => 'ignored']);

    $received = [];
    $pubsub->subscribe('module.enabled', function (array $payload) use (&$received): void {
        $received[] = $payload;
    }, maxPolls: 1);

    expect($received)->toBe([
        ['module' => 'crm'],
        ['module' => 'tasks'],
    ]);
});

it('persists rows in the configured table with a bigint cursor', function (): void {
    $pubsub = new DatabasePollPubSub(connection: PUBSUB_DB_POLL_TEST_CONNECTION);
    $pubsub->publish('module.enabled', ['module' => 'crm']);

    $row = pubsubPg()->table(Config::pubsubMessagesTable())->first();

    expect($row->channel)->toBe('module.enabled')
        ->and(json_decode((string) $row->payload, true))->toBe(['module' => 'crm'])
        ->and($row->id)->toBeInt();
});

it('AC-25(b): PubSubManager resolves DatabasePollPubSub for the database driver', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Database]);
    /** @var PubSubManager $manager */
    $manager = app(PubSub::class);

    $method = new ReflectionMethod(PubSubManager::class, 'driver');
    $method->setAccessible(true);

    expect($method->invoke($manager))->toBeInstanceOf(DatabasePollPubSub::class);
});
