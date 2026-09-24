<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Credentials\CredentialIssuanceDenied;
use CoreX\Tenancy\Membership\Credentials\FencedCredentialIssuer;
use CoreX\Tenancy\Membership\Persistence\PairKey;
use CoreX\Tenancy\Membership\Persistence\PairStore;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\CredentialFixture;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

it('rolls back the session when the paired remember insert conflicts', function (): void {
    PairStoreFixture::create();
    CredentialFixture::create();
    $key = new PairKey(accountId: '22222222-2222-4222-8222-222222222222', identityId: '11111111-1111-4111-8111-111111111111');
    $operation = '33333333-3333-4333-8333-333333333333';
    $pairs = new PairStore(connection: DB::connection());
    $issuer = new FencedCredentialIssuer(connection: DB::connection(), pairs: $pairs);

    try {
        DB::table('membership_credential_remembers')->insert([
            'remember_token' => 'already-used',
            'account_id' => $key->accountId,
            'identity_id' => $key->identityId,
            'operation_id' => $operation,
        ]);
        $pairs->admit(key: $key, operationId: $operation);

        expect(fn (): null => $issuer->issue(
            key: $key,
            operationId: $operation,
            sessionId: 'must-rollback',
            rememberToken: 'already-used',
            metadata: ['guard' => 'reference'],
        ))->toThrow(CredentialIssuanceDenied::class);

        expect(DB::table('membership_credential_sessions')->count())->toBe(0)
            ->and($pairs->read(key: $key)->applicationFence)->toBeNull();
    } finally {
        CredentialFixture::drop();
        PairStoreFixture::drop();
    }
})->group('pg');
