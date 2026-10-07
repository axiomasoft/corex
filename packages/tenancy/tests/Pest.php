<?php

declare(strict_types=1);

use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Tests\TestCase;

uses(TestCase::class)->group('pg')->in('Feature');

uses()->group('pg')->in('Pg');

/**
 * Enters the account's tenant context through the package engine: resolves the AccountRef from the central database.
 */
function initTenantContext(Account|string $account): void
{
    $id = is_string($account) ? $account : (string) $account->id;
    $ref = resolve(AccountRead::class)->find($id, (string) config('corex-tenancy.central_connection', 'pgsql'))
        ?? throw new RuntimeException("Account [{$id}] is not in the central database.");

    resolve(DatabaseTenancyManager::class)->initialize($ref);
}

/** Always the real engine, even while a test swapped the `TenancyManager` binding for a stub. */
function endTenantContext(): void
{
    resolve(DatabaseTenancyManager::class)->end();
}
