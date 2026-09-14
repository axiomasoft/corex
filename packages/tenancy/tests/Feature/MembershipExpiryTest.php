<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Internal\Expiry\ExpiryScheduler;
use CoreX\Tenancy\Membership\Internal\Persistence\PairKey;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use CoreX\Tenancy\Membership\Internal\Timing\ClockReading;
use CoreX\Tenancy\Membership\Internal\Timing\Elapsed;
use CoreX\Tenancy\Membership\Internal\Timing\Lease;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use Illuminate\Support\Facades\DB;

const EXPIRY_ACCOUNT = '22222222-2222-4222-8222-222222222222';
const EXPIRY_IDENTITY = '11111111-1111-4111-8111-111111111111';
const EXPIRY_OPERATION = '33333333-3333-4333-8333-333333333333';
const EXPIRY_BARRIER = '55555555-5555-4555-8555-555555555555';

beforeEach(function (): void {
    PairStoreFixture::create();
    $this->expiryKey = new PairKey(accountId: EXPIRY_ACCOUNT, identityId: EXPIRY_IDENTITY);
    $this->expiryStore = new PairStore(connection: DB::connection());
});

afterEach(function (): void {
    PairStoreFixture::drop();
});

it('denies mismatched, restarted, and equality-bound clock readings', function (): void {
    $start = new ClockReading(domainId: 'fixture-a', processId: 'worker-a', ticks: 0, ticksPerSecond: 10, errorTicks: 0);
    $end = new ClockReading(domainId: 'fixture-a', processId: 'worker-a', ticks: 440, ticksPerSecond: 10, errorTicks: 0);

    expect(Elapsed::upperBoundSeconds(started: $start, observed: $end))->toBe(44)
        ->and(Lease::canCommit(elapsedUpperSeconds: 44, remainingCommitUpperSeconds: 0))->toBeTrue()
        ->and(Lease::canCommit(elapsedUpperSeconds: 44, remainingCommitUpperSeconds: 1))->toBeFalse()
        ->and(fn () => Elapsed::upperBoundSeconds(started: $start, observed: new ClockReading(domainId: 'fixture-b', processId: 'worker-a', ticks: 1, ticksPerSecond: 10, errorTicks: 0)))->toThrow(LogicException::class);
});

it('cancels a paused owner before its delayed commit can publish', function (): void {
    $this->expiryStore->admit(key: $this->expiryKey, operationId: EXPIRY_OPERATION);
    $state = (new ExpiryScheduler(pairs: $this->expiryStore))->expire(key: $this->expiryKey, barrierId: EXPIRY_BARRIER);

    expect($state->denied)->toBeTrue()
        ->and($this->expiryStore->commit(key: $this->expiryKey, operationId: EXPIRY_OPERATION))->toBeFalse();
});
