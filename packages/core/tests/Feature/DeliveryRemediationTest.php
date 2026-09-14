<?php

declare(strict_types=1);

use CoreX\Audit\QueuedAuditLogger;
use CoreX\Contracts\AuditEntry;
use CoreX\Enums\ActorType;
use CoreX\PubSub\DatabasePollPubSub;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql', 'corex.audit.enabled' => true, 'queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations/pg'), '--realpath' => true, '--force' => true])->assertSuccessful();
});

it('records committed audit only after the outer transaction succeeds', function (): void {
    $logger = new QueuedAuditLogger('pgsql');
    foreach ([false, true] as $commit) {
        DB::beginTransaction();
        DB::transaction(fn () => $logger->record(new AuditEntry(action: 'changed', actorType: ActorType::System)));
        expect(DB::table('sys_audit_log')->count())->toBe(0);
        $commit ? DB::commit() : DB::rollBack();
    }
    expect(DB::table('sys_audit_log')->count())->toBe(1);
});

it('serializes publishers so a cursor cannot overtake an uncommitted message', function (): void {
    config(['database.connections.publisher_test' => config('database.connections.pgsql')]);
    $other = DB::connection('publisher_test');
    $other->beginTransaction();
    $other->statement("SET LOCAL lock_timeout = '50ms'");
    $first = new DatabasePollPubSub('pgsql');
    $second = new DatabasePollPubSub('publisher_test');
    DB::beginTransaction();

    try {
        $first->publish('ordered', ['message' => 'first']);
        expect(fn () => $second->publish('ordered', ['message' => 'second']))->toThrow(QueryException::class);
    } finally {
        DB::commit();
    }
    $second->publish('ordered', ['message' => 'second']);
    $other->commit();
    $received = [];
    $first->subscribe('ordered', function (array $payload) use (&$received): void {
        $received[] = $payload['message'];
    }, maxPolls: 1, subscriber: 'regression', replayBacklog: true);
    expect($received)->toBe(['first', 'second']);
    DB::purge('publisher_test');
});
