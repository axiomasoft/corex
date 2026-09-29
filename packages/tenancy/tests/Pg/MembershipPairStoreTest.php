<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Data\PairKey;
use CoreX\Tenancy\Membership\Data\PairWitness;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

const PG_PAIR_ACCOUNT = '22222222-2222-4222-8222-222222222222';
const PG_PAIR_IDENTITY = '11111111-1111-4111-8111-111111111111';
const PG_PAIR_OPERATION_A = '33333333-3333-4333-8333-333333333333';
const PG_PAIR_OPERATION_B = '44444444-4444-4444-8444-444444444444';

beforeEach(function (): void {
    PairStoreFixture::create();
    $this->pairStore = new PairStore(connection: DB::connection());
    $this->pairKey = new PairKey(accountId: PG_PAIR_ACCOUNT, identityId: PG_PAIR_IDENTITY);
});

afterEach(function (): void {
    PairStoreFixture::drop();
});

it('roundtrips numeric(20,0) values without integer narrowing or lexical comparison', function (): void {
    $this->pairStore->admit(key: $this->pairKey, operationId: PG_PAIR_OPERATION_A);
    $state = $this->pairStore->apply(key: $this->pairKey, operationId: PG_PAIR_OPERATION_A, revision: '18446744073709551615', revokeGeneration: '9007199254740993');

    expect($state->revision)->toBe('18446744073709551615')
        ->and($state->revokeGeneration)->toBe('9007199254740993');
})->group('pg');

it('uses an atomic PostgreSQL compare-and-swap and closes restore until a nonrollback witness is current', function (): void {
    $this->pairStore->admit(key: $this->pairKey, operationId: PG_PAIR_OPERATION_A);
    expect($this->pairStore->commit(key: $this->pairKey, operationId: PG_PAIR_OPERATION_A))->toBeTrue();

    $newer = $this->pairStore->admit(key: $this->pairKey, operationId: PG_PAIR_OPERATION_B);
    $restored = $this->pairStore->restore(key: $this->pairKey, witness: new PairWitness(revision: '0', revokeGeneration: '0', localEpoch: '0', operationFence: $newer->operationFence, applicationFence: $newer->applicationFence));

    expect($newer->applicationFence)->toBe('1')
        ->and($restored->applicationFence)->toBeNull()
        ->and($restored->denied)->toBeTrue();
})->group('pg');
