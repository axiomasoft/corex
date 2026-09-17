<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Internal\Credentials\CredentialIssuanceDenied;
use CoreX\Tenancy\Membership\Internal\Credentials\FinalWriteAdmission;
use CoreX\Tenancy\Membership\Internal\Persistence\PairKey;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use CoreX\Tenancy\Membership\Internal\Timing\ClockReading;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\CredentialFixture;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

beforeEach(function (): void {
    PairStoreFixture::create();
    CredentialFixture::create();
    $this->finalWriteKey = new PairKey(accountId: '22222222-2222-4222-8222-222222222222', identityId: '11111111-1111-4111-8111-111111111111');
    $this->finalWriteOperation = '33333333-3333-4333-8333-333333333333';
    $this->finalWritePairs = new PairStore(connection: DB::connection());
    $this->finalWritePairs->admit(key: $this->finalWriteKey, operationId: $this->finalWriteOperation);
    $this->finalWritePairs->apply(key: $this->finalWriteKey, operationId: $this->finalWriteOperation, revision: '1', revokeGeneration: '1');
    $this->finalWritePairs->regrant(key: $this->finalWriteKey, operationId: $this->finalWriteOperation);
});

afterEach(function (): void {
    CredentialFixture::drop();
    PairStoreFixture::drop();
});

it('denies construction when the pair store is not the exact writer connection', function (): void {
    $otherConnection = clone DB::connection();
    $otherPairs = new PairStore(connection: $otherConnection);

    expect(fn (): FinalWriteAdmission => new FinalWriteAdmission(
        connection: DB::connection(),
        pairs: $otherPairs,
        clock: static fn (): ClockReading => clockReading(ticks: 0),
        remainingCommitUpperSeconds: 1,
    ))->toThrow(CredentialIssuanceDenied::class);
});

it('commits a final writer callback and its application fence in one connection transaction', function (): void {
    $admission = finalWriteAdmission(pairs: $this->finalWritePairs, readings: [clockReading(ticks: 1), clockReading(ticks: 2)]);

    $admission->write(
        key: $this->finalWriteKey,
        operationId: $this->finalWriteOperation,
        readStarted: clockReading(ticks: 0),
        revokeGeneration: '1',
        localEpoch: '0',
        write: function (ConnectionInterface $connection): void {
            $connection->insert(
                query: 'INSERT INTO membership_credential_sessions (session_id, account_id, identity_id, operation_id, metadata) VALUES (?, ?, ?, ?, ?)',
                bindings: ['final-write', $this->finalWriteKey->accountId, $this->finalWriteKey->identityId, $this->finalWriteOperation, '{"boundary":"final"}'],
            );
        },
    );

    expect(DB::table('membership_credential_sessions')->count())->toBe(1)
        ->and($this->finalWritePairs->read(key: $this->finalWriteKey)->applicationFence)->toBe('1');
});

it('rolls back a writer callback when the fresh post-write clock exceeds the lease', function (): void {
    $admission = finalWriteAdmission(pairs: $this->finalWritePairs, readings: [clockReading(ticks: 1), clockReading(ticks: 450)]);

    expect(fn (): null => $admission->write(
        key: $this->finalWriteKey,
        operationId: $this->finalWriteOperation,
        readStarted: clockReading(ticks: 0),
        revokeGeneration: '1',
        localEpoch: '0',
        write: function (ConnectionInterface $connection): void {
            $connection->insert(
                query: 'INSERT INTO membership_credential_sessions (session_id, account_id, identity_id, operation_id, metadata) VALUES (?, ?, ?, ?, ?)',
                bindings: ['expired-write', $this->finalWriteKey->accountId, $this->finalWriteKey->identityId, $this->finalWriteOperation, '{}'],
            );
        },
    ))->toThrow(CredentialIssuanceDenied::class);

    expect(DB::table('membership_credential_sessions')->count())->toBe(0)
        ->and($this->finalWritePairs->read(key: $this->finalWriteKey)->applicationFence)->toBeNull();
});

it('rolls back a writer callback when its clock domain is no longer qualified', function (): void {
    $admission = finalWriteAdmission(pairs: $this->finalWritePairs, readings: [clockReading(ticks: 1), clockReading(ticks: 2, domainId: 'restarted')]);

    expect(fn (): null => $admission->write(
        key: $this->finalWriteKey,
        operationId: $this->finalWriteOperation,
        readStarted: clockReading(ticks: 0),
        revokeGeneration: '1',
        localEpoch: '0',
        write: function (ConnectionInterface $connection): void {
            $connection->insert(
                query: 'INSERT INTO membership_credential_sessions (session_id, account_id, identity_id, operation_id, metadata) VALUES (?, ?, ?, ?, ?)',
                bindings: ['domain-lost', $this->finalWriteKey->accountId, $this->finalWriteKey->identityId, $this->finalWriteOperation, '{}'],
            );
        },
    ))->toThrow(CredentialIssuanceDenied::class);

    expect(DB::table('membership_credential_sessions')->count())->toBe(0)
        ->and($this->finalWritePairs->read(key: $this->finalWriteKey)->applicationFence)->toBeNull();
});

/** @param list<ClockReading> $readings */
function finalWriteAdmission(PairStore $pairs, array $readings): FinalWriteAdmission
{
    return new FinalWriteAdmission(
        connection: DB::connection(),
        pairs: $pairs,
        clock: static function () use (&$readings): ClockReading {
            return array_shift($readings) ?? throw new LogicException('Test clock exhausted.');
        },
        remainingCommitUpperSeconds: 1,
    );
}

function clockReading(int $ticks, string $domainId = 'test-domain'): ClockReading
{
    return new ClockReading(domainId: $domainId, processId: 'test-process', ticks: $ticks, ticksPerSecond: 10, errorTicks: 0);
}
