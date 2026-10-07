<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Data\PairKey;
use CoreX\Tenancy\Membership\Internal\Expiration\ExpiryScheduler;
use CoreX\Tenancy\Membership\Internal\Persistence\PairStore;
use CoreX\Tenancy\Tests\Support\Membership\PairStoreFixture;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

it('makes PostgreSQL expiry win over a delayed fixture commit', function (): void {
    PairStoreFixture::create();
    $store = new PairStore(connection: DB::connection());
    $key = new PairKey(accountId: '22222222-2222-4222-8222-222222222222', identityId: '11111111-1111-4111-8111-111111111111');
    $operation = '33333333-3333-4333-8333-333333333333';

    try {
        $store->admit(key: $key, operationId: $operation);
        new ExpiryScheduler(pairs: $store)->expire(key: $key, barrierId: '55555555-5555-4555-8555-555555555555');

        expect($store->commit(key: $key, operationId: $operation))->toBeFalse();
    } finally {
        PairStoreFixture::drop();
    }
})->group('pg');
