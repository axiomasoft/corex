<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Inbox\DurableInbox;
use CoreX\Tenancy\Membership\Inbox\InboxConflict;
use CoreX\Tenancy\Membership\Persistence\PairKey;
use CoreX\Tenancy\Membership\Persistence\PairStore;
use CoreX\Tenancy\Membership\Persistence\PairStoreViolation;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

const PHYSICAL_PAIR_ACCOUNT = '22222222-2222-4222-8222-222222222222';
const PHYSICAL_PAIR_IDENTITY = '11111111-1111-4111-8111-111111111111';
const PHYSICAL_PAIR_OPERATION = '33333333-3333-4333-8333-333333333333';
const PHYSICAL_INBOX_OWNER = '44444444-4444-4444-8444-444444444444';

beforeEach(function (): void {
    Schema::dropIfExists('tnt_membership_inbox');
    Schema::dropIfExists('tnt_membership_pairs');

    /** @var Migration $migration */
    $migration = require __DIR__.'/../../database/migrations/tenant/2026_09_15_000700_create_membership_adapter_tables.php';
    $migration->up();
    $this->migration = $migration;
});

afterEach(function (): void {
    DB::table('tnt_membership_inbox')->delete();
    DB::table('tnt_membership_pairs')->delete();
    $this->migration->down();
});

it('keeps the physical pair mapping denied until a canonical snapshot is applied', function (): void {
    $connection = DB::connection();
    $store = new PairStore(connection: $connection, table: 'tnt_membership_pairs');
    $key = new PairKey(accountId: PHYSICAL_PAIR_ACCOUNT, identityId: PHYSICAL_PAIR_IDENTITY);

    $admitted = $store->admit(key: $key, operationId: PHYSICAL_PAIR_OPERATION);

    expect($admitted->denied)->toBeTrue()
        ->and($store->isFor(connection: $connection))->toBeTrue()
        ->and(fn (): mixed => $store->apply(key: $key, operationId: PHYSICAL_PAIR_OPERATION, revision: '1', revokeGeneration: '0'))->toThrow(PairStoreViolation::class);

    $applied = $store->apply(
        key: $key,
        operationId: PHYSICAL_PAIR_OPERATION,
        revision: '1',
        revokeGeneration: '0',
        snapshotDigest: str_repeat('a', 64),
    );

    expect($applied->snapshotDigest)->toBe(str_repeat('a', 64))
        ->and($store->regrant(key: $key, operationId: PHYSICAL_PAIR_OPERATION)->denied)->toBeFalse()
        ->and(fn (): mixed => $store->apply(key: $key, operationId: PHYSICAL_PAIR_OPERATION, revision: '1', revokeGeneration: '0', snapshotDigest: str_repeat('b', 64)))->toThrow(PairStoreViolation::class);
})->group('pg');

it('uses the physical inbox atomically and refuses non-UUID workers', function (): void {
    $fixture = new TransportFixture;
    $inbox = new DurableInbox(connection: DB::connection(), table: 'tnt_membership_inbox');

    try {
        $trigger = $fixture->parser()->parse(compact: $fixture->sign(claims: $fixture->eventClaims(), event: true), now: $fixture->now);
        $inbox->admit(trigger: $trigger);
        $inbox->admit(trigger: $trigger);

        expect(DB::table('tnt_membership_inbox')->count())->toBe(1)
            ->and(fn (): bool => $inbox->claim(eventId: $trigger->eventId, owner: 'worker-a', now: 10))->toThrow(InboxConflict::class)
            ->and($inbox->claim(eventId: $trigger->eventId, owner: PHYSICAL_INBOX_OWNER, now: 10))->toBeTrue()
            ->and($inbox->acknowledge(eventId: $trigger->eventId, owner: PHYSICAL_INBOX_OWNER, now: 10))->toBeTrue();
    } finally {
        $fixture->cleanup();
    }
})->group('pg');

it('refuses a populated migration rollback', function (): void {
    DB::table('tnt_membership_pairs')->insert([
        'account_id' => PHYSICAL_PAIR_ACCOUNT,
        'identity_id' => PHYSICAL_PAIR_IDENTITY,
        'revision' => 0,
        'revoke_generation' => 0,
        'local_epoch' => 0,
        'operation_fence' => 0,
        'denied' => true,
    ]);

    expect(fn (): mixed => $this->migration->down())->toThrow(RuntimeException::class);
})->group('pg');
