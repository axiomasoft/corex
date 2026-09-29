<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Data\PairKey;
use CoreX\Tenancy\Membership\Data\PairWitness;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Exceptions\PairStoreViolation;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use Illuminate\Support\Facades\DB;

const PAIR_ACCOUNT = '22222222-2222-4222-8222-222222222222';
const PAIR_IDENTITY = '11111111-1111-4111-8111-111111111111';
const PAIR_OPERATION_A = '33333333-3333-4333-8333-333333333333';
const PAIR_OPERATION_B = '44444444-4444-4444-8444-444444444444';
const PAIR_BARRIER_A = '55555555-5555-4555-8555-555555555555';

beforeEach(function (): void {
    PairStoreFixture::create();
    $this->pairKey = new PairKey(accountId: PAIR_ACCOUNT, identityId: PAIR_IDENTITY);
    $this->pairStore = new PairStore(connection: DB::connection());
});

afterEach(function (): void {
    PairStoreFixture::drop();
});

it('keeps exact counter boundaries and rejects every noncanonical value before SQL', function (string $value, bool $accepted): void {
    if (! $accepted) {
        expect(fn () => $this->pairStore->apply(key: $this->pairKey, operationId: PAIR_OPERATION_A, revision: $value, revokeGeneration: '0'))->toThrow(CentralProtocolViolation::class);

        return;
    }

    $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_A);
    $state = $this->pairStore->apply(key: $this->pairKey, operationId: PAIR_OPERATION_A, revision: $value, revokeGeneration: '0');

    expect($state->revision)->toBe($value);
})->with([
    ['0', true], ['9', true], ['10', true], ['9007199254740991', true], ['9007199254740992', true], ['9007199254740993', true], ['9223372036854775807', true], ['9223372036854775808', true], ['18446744073709551615', true],
    ['-1', false], ['01', false], ['1.0', false], ['1e2', false], ['NaN', false], ['Infinity', false], ['18446744073709551616', false],
]);

it('makes an operation replay idempotent and preserves a committed application fence through newer admission', function (): void {
    $first = $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_A);
    $replayed = $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_A);

    expect($first->operationFence)->toBe('1')
        ->and($replayed->operationFence)->toBe('1')
        ->and($this->pairStore->commit(key: $this->pairKey, operationId: PAIR_OPERATION_A))->toBeTrue();

    $second = $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_B);

    expect($second->operationFence)->toBe('2')
        ->and($second->applicationFence)->toBe('1');

    expect(fn () => $this->pairStore->commit(key: $this->pairKey, operationId: PAIR_OPERATION_A))
        ->toThrow(PairStoreViolation::class);
});

it('increments one barrier epoch once and cancels an older committed application fence', function (): void {
    $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_A);
    $this->pairStore->commit(key: $this->pairKey, operationId: PAIR_OPERATION_A);

    $first = $this->pairStore->invalidate(key: $this->pairKey, barrierId: PAIR_BARRIER_A);
    $replayed = $this->pairStore->invalidate(key: $this->pairKey, barrierId: PAIR_BARRIER_A);

    expect($first->localEpoch)->toBe('1')
        ->and($first->applicationFence)->toBeNull()
        ->and($first->denied)->toBeTrue()
        ->and($replayed->localEpoch)->toBe('1');
});

it('cannot regrant an applied operation after a newer revoke barrier', function (): void {
    $this->pairStore->admit($this->pairKey, PAIR_OPERATION_A);
    $this->pairStore->apply($this->pairKey, PAIR_OPERATION_A, '1', '0');
    $this->pairStore->invalidate($this->pairKey, PAIR_BARRIER_A);
    expect(fn () => $this->pairStore->regrant($this->pairKey, PAIR_OPERATION_A))->toThrow(PairStoreViolation::class)
        ->and(fn () => $this->pairStore->apply($this->pairKey, PAIR_OPERATION_A, '1', '0'))->toThrow(PairStoreViolation::class)
        ->and($this->pairStore->commit($this->pairKey, PAIR_OPERATION_A))->toBeFalse();
});

it('denies counter regression stale compare-and-swap and an unavailable or behind restore witness', function (): void {
    $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_A);
    $this->pairStore->apply(key: $this->pairKey, operationId: PAIR_OPERATION_A, revision: '10', revokeGeneration: '3');
    $this->pairStore->admit(key: $this->pairKey, operationId: PAIR_OPERATION_B);

    expect(fn () => $this->pairStore->apply(key: $this->pairKey, operationId: PAIR_OPERATION_B, revision: '9', revokeGeneration: '3'))->toThrow(PairStoreViolation::class)
        ->and(fn () => $this->pairStore->commit(key: $this->pairKey, operationId: PAIR_OPERATION_A))->toThrow(PairStoreViolation::class)
        ->and(fn () => $this->pairStore->restore(key: $this->pairKey, witness: null))->toThrow(PairStoreViolation::class)
        ->and(fn () => $this->pairStore->restore(key: $this->pairKey, witness: new PairWitness(revision: '9', revokeGeneration: '3', localEpoch: '0', operationFence: '2', applicationFence: null)))->toThrow(PairStoreViolation::class);
});
