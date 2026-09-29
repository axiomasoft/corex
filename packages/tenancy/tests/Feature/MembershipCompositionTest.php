<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Data\PairKey;
use CoreX\Tenancy\Membership\Data\PairState;
use CoreX\Tenancy\Membership\Data\PairWitness;
use CoreX\Tenancy\Membership\Enums\MembershipState;
use CoreX\Tenancy\Membership\Exceptions\PairStoreViolation;
use CoreX\Tenancy\Membership\Exceptions\ProjectionDenied;
use CoreX\Tenancy\Membership\Internal\Credentials\CredentialCookieQueue;
use CoreX\Tenancy\Membership\Internal\Credentials\FencedCredentialIssuer;
use CoreX\Tenancy\Membership\Internal\Expiration\ExpiryScheduler;
use CoreX\Tenancy\Membership\Internal\Inbox\DurableInbox;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use CoreX\Tenancy\Membership\Internal\Projection\ReferenceMembershipProjection;
use CoreX\Tenancy\Membership\Internal\Transport\MembershipSnapshot;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\CredentialFixture;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\InboxFixture;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;
use Illuminate\Support\Facades\DB;

const COMPOSITION_ACCOUNT_A = '22222222-2222-4222-8222-222222222222';
const COMPOSITION_ACCOUNT_B = '66666666-6666-4666-8666-666666666666';
const COMPOSITION_IDENTITY = '11111111-1111-4111-8111-111111111111';
const COMPOSITION_OPERATION_A = '33333333-3333-4333-8333-333333333333';
const COMPOSITION_OPERATION_A_REGRANT = '44444444-4444-4444-8444-444444444444';
const COMPOSITION_OPERATION_B = '55555555-5555-4555-8555-555555555555';
const COMPOSITION_BARRIER_A = '77777777-7777-4777-8777-777777777777';

beforeEach(function (): void {
    PairStoreFixture::create();
    CredentialFixture::create();
    InboxFixture::create();

    $this->compositionTransport = new TransportFixture;
    $this->compositionPairs = new PairStore(connection: DB::connection());
    $this->composition = new ReferenceMembershipProjection(
        connection: DB::connection(),
        pairs: $this->compositionPairs,
        credentials: new FencedCredentialIssuer(connection: DB::connection(), pairs: $this->compositionPairs),
        expiry: new ExpiryScheduler(pairs: $this->compositionPairs),
        inbox: new DurableInbox(connection: DB::connection()),
    );
});

afterEach(function (): void {
    $this->compositionTransport->cleanup();
    InboxFixture::drop();
    CredentialFixture::drop();
    PairStoreFixture::drop();
});

function compositionSnapshot(string $accountId, string $revision = '10', string $generation = '3'): MembershipSnapshot
{
    return new MembershipSnapshot(
        profileVersion: 'corex-membership/1',
        requestId: TransportFixture::REQUEST,
        identityId: COMPOSITION_IDENTITY,
        accountId: $accountId,
        product: TransportFixture::PRODUCT,
        membershipId: TransportFixture::MEMBERSHIP,
        state: MembershipState::Active,
        revision: $revision,
        revokeGeneration: $generation,
        readStartedAt: new DateTimeImmutable('2026-09-13T00:00:00Z'),
        observedAt: new DateTimeImmutable('2026-09-13T00:00:01Z'),
    );
}

/** @return array{projection: ReferenceMembershipProjection, cookies: CredentialCookieQueue} */
function grantComposition(string $accountId, string $operationId, string $sessionId, string $revision = '10', string $generation = '3'): array
{
    /** @var ReferenceMembershipProjection $projection */
    $projection = test()->composition;
    $cookies = new CredentialCookieQueue;
    $projection->grant(
        key: new PairKey(accountId: $accountId, identityId: COMPOSITION_IDENTITY),
        snapshot: compositionSnapshot(accountId: $accountId, revision: $revision, generation: $generation),
        operationId: $operationId,
        sessionId: $sessionId,
        rememberToken: 'remember-'.$sessionId,
        metadata: ['guard' => 'reference'],
        elapsedUpperSeconds: 1,
        remainingCommitUpperSeconds: 1,
        cookies: $cookies,
    );

    return ['projection' => $projection, 'cookies' => $cookies];
}

it('revokes only account A, cleans its credentials, and requires a fresh fenced regrant', function (): void {
    $a = grantComposition(accountId: COMPOSITION_ACCOUNT_A, operationId: COMPOSITION_OPERATION_A, sessionId: 'a-old');
    grantComposition(accountId: COMPOSITION_ACCOUNT_B, operationId: COMPOSITION_OPERATION_B, sessionId: 'b-live');

    $revoked = $this->composition->revoke(
        key: new PairKey(accountId: COMPOSITION_ACCOUNT_A, identityId: COMPOSITION_IDENTITY),
        barrierId: COMPOSITION_BARRIER_A,
        cookies: $a['cookies'],
    );

    expect($revoked->denied)->toBeTrue()
        ->and($a['cookies']->release())->toBe([])
        ->and(DB::table('membership_credential_sessions')->where('account_id', COMPOSITION_ACCOUNT_A)->count())->toBe(0)
        ->and(DB::table('membership_credential_remembers')->where('account_id', COMPOSITION_ACCOUNT_A)->count())->toBe(0)
        ->and(DB::table('membership_credential_sessions')->where('account_id', COMPOSITION_ACCOUNT_B)->count())->toBe(1);

    $fresh = grantComposition(
        accountId: COMPOSITION_ACCOUNT_A,
        operationId: COMPOSITION_OPERATION_A_REGRANT,
        sessionId: 'a-fresh',
        revision: '11',
        generation: '4',
    );

    expect($fresh['cookies']->release())->toBe([
        ['name' => 'session', 'value' => 'a-fresh'],
        ['name' => 'remember', 'value' => 'remember-a-fresh'],
    ])->and(DB::table('membership_credential_sessions')->where('account_id', COMPOSITION_ACCOUNT_A)->value('session_id'))->toBe('a-fresh');

    expect(fn (): array => grantComposition(
        accountId: COMPOSITION_ACCOUNT_A,
        operationId: COMPOSITION_OPERATION_A,
        sessionId: 'a-delayed',
        revision: '10',
        generation: '3',
    ))->toThrow(PairStoreViolation::class)
        ->and(DB::table('membership_credential_sessions')->where('account_id', COMPOSITION_ACCOUNT_A)->pluck('session_id')->all())->toBe(['a-fresh']);
});

it('denies inactive, cross-account, and lease-expired entrances before issuing credentials', function (): void {
    $cookies = new CredentialCookieQueue;
    $inactive = compositionSnapshot(accountId: COMPOSITION_ACCOUNT_A);
    $inactive = new MembershipSnapshot(
        profileVersion: $inactive->profileVersion,
        requestId: $inactive->requestId,
        identityId: $inactive->identityId,
        accountId: $inactive->accountId,
        product: $inactive->product,
        membershipId: null,
        state: MembershipState::Revoked,
        revision: $inactive->revision,
        revokeGeneration: $inactive->revokeGeneration,
        readStartedAt: $inactive->readStartedAt,
        observedAt: $inactive->observedAt,
    );

    expect(fn (): PairState => $this->composition->grant(
        key: new PairKey(accountId: COMPOSITION_ACCOUNT_A, identityId: COMPOSITION_IDENTITY),
        snapshot: $inactive,
        operationId: COMPOSITION_OPERATION_A,
        sessionId: 'never-issued',
        rememberToken: null,
        metadata: [],
        elapsedUpperSeconds: 44,
        remainingCommitUpperSeconds: 1,
        cookies: $cookies,
    ))->toThrow(ProjectionDenied::class)
        ->and($cookies->release())->toBe([])
        ->and(DB::table('membership_credential_sessions')->count())->toBe(0);
});

it('keeps a verified trigger inert until its fenced refresh pull completes', function (): void {
    $trigger = $this->compositionTransport->parser()->parse(
        compact: $this->compositionTransport->sign(claims: $this->compositionTransport->eventClaims(), event: true),
        now: $this->compositionTransport->now,
    );
    $this->composition->admitTrigger(trigger: $trigger);

    expect(DB::table('membership_trigger_inbox')->count())->toBe(1)
        ->and(DB::table('membership_credential_sessions')->count())->toBe(0)
        ->and($this->composition->refresh(
            eventId: $trigger->eventId,
            owner: 'fixture-worker',
            now: 10,
            pull: static fn (): null => null,
        ))->toBeTrue();
});

it('keeps restore denied and clears prepared cookies until an explicit new active pull', function (): void {
    $cookies = new CredentialCookieQueue;
    $cookies->queueSession(value: 'stale');

    $state = $this->composition->restore(
        key: new PairKey(accountId: COMPOSITION_ACCOUNT_A, identityId: COMPOSITION_IDENTITY),
        witness: new PairWitness(revision: '0', revokeGeneration: '0', localEpoch: '0', operationFence: '0', applicationFence: null),
        cookies: $cookies,
    );

    expect($state->denied)->toBeTrue()
        ->and($cookies->release())->toBe([])
        ->and(DB::table('membership_credential_sessions')->count())->toBe(0);
});

it('refuses a regrant that did not apply the current authority snapshot', function (): void {
    $key = new PairKey(accountId: COMPOSITION_ACCOUNT_A, identityId: COMPOSITION_IDENTITY);
    $this->compositionPairs->admit(key: $key, operationId: COMPOSITION_OPERATION_A);

    expect(fn (): PairState => $this->compositionPairs->regrant(key: $key, operationId: COMPOSITION_OPERATION_A))
        ->toThrow(PairStoreViolation::class)
        ->and($this->compositionPairs->read(key: $key)->denied)->toBeFalse();
});
