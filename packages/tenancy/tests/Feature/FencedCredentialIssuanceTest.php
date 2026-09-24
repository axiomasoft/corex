<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Credentials\CredentialCookieQueue;
use CoreX\Tenancy\Membership\Credentials\CredentialIssuanceDenied;
use CoreX\Tenancy\Membership\Credentials\FencedCredentialIssuer;
use CoreX\Tenancy\Membership\Persistence\PairKey;
use CoreX\Tenancy\Membership\Persistence\PairStore;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\CredentialFixture;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use Illuminate\Support\Facades\DB;

const CREDENTIAL_ACCOUNT = '22222222-2222-4222-8222-222222222222';
const CREDENTIAL_IDENTITY = '11111111-1111-4111-8111-111111111111';
const CREDENTIAL_OPERATION = '33333333-3333-4333-8333-333333333333';
const CREDENTIAL_BARRIER = '55555555-5555-4555-8555-555555555555';

beforeEach(function (): void {
    PairStoreFixture::create();
    CredentialFixture::create();
    $this->credentialKey = new PairKey(accountId: CREDENTIAL_ACCOUNT, identityId: CREDENTIAL_IDENTITY);
    $this->credentialPairs = new PairStore(connection: DB::connection());
    $this->credentialIssuer = new FencedCredentialIssuer(connection: DB::connection(), pairs: $this->credentialPairs);
});

afterEach(function (): void {
    CredentialFixture::drop();
    PairStoreFixture::drop();
});

it('writes session, remember, and metadata as one fenced transaction', function (): void {
    $this->credentialPairs->admit(key: $this->credentialKey, operationId: CREDENTIAL_OPERATION);

    $this->credentialIssuer->issue(
        key: $this->credentialKey,
        operationId: CREDENTIAL_OPERATION,
        sessionId: 'session-a',
        rememberToken: 'remember-a',
        metadata: ['guard' => 'reference', 'rotation' => 1],
    );

    expect(DB::table('membership_credential_sessions')->count())->toBe(1)
        ->and(DB::table('membership_credential_remembers')->count())->toBe(1)
        ->and($this->credentialPairs->read(key: $this->credentialKey)->applicationFence)->toBe('1');
});

it('does not issue a recaller when remember is disabled', function (): void {
    $this->credentialPairs->admit(key: $this->credentialKey, operationId: CREDENTIAL_OPERATION);

    $this->credentialIssuer->issue(
        key: $this->credentialKey,
        operationId: CREDENTIAL_OPERATION,
        sessionId: 'session-without-remember',
        rememberToken: null,
        metadata: ['guard' => 'reference'],
    );

    expect(DB::table('membership_credential_sessions')->count())->toBe(1)
        ->and(DB::table('membership_credential_remembers')->count())->toBe(0);
});

it('clears every prepared credential cookie after a final denial', function (): void {
    $this->credentialPairs->admit(key: $this->credentialKey, operationId: CREDENTIAL_OPERATION);
    $this->credentialPairs->invalidate(key: $this->credentialKey, barrierId: CREDENTIAL_BARRIER);
    $cookies = new CredentialCookieQueue;
    $cookies->queueSession(value: 'session-a');
    $cookies->queueRemember(value: 'remember-a');

    try {
        $this->credentialIssuer->issue(
            key: $this->credentialKey,
            operationId: CREDENTIAL_OPERATION,
            sessionId: 'session-a',
            rememberToken: 'remember-a',
            metadata: ['guard' => 'reference'],
        );
    } catch (CredentialIssuanceDenied) {
        $cookies->deny();
    }

    expect($cookies->release())->toBe([])
        ->and(DB::table('membership_credential_sessions')->count())->toBe(0)
        ->and(DB::table('membership_credential_remembers')->count())->toBe(0);
});
