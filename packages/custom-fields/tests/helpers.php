<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

function jsonbScope(?string $bindingId = null, ?string $restoreEpoch = null): DataScope
{
    return CallerWorkScopeFixture::forContext(jsonbContext(), 'user-1', $restoreEpoch ?? str_repeat('a', 64), $bindingId);
}

function jsonbContext(): TenantContext
{
    return new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true);
}

function jsonbCurrent(): CallerWorkScopeFixture
{
    return new CallerWorkScopeFixture(jsonbContext(), static fn (): ConnectionInterface => DB::connection(), jsonbScope());
}

function jsonbAuthorizer(?Closure $authorize = null): DataScopeAuthorizer
{
    if ($authorize === null) {
        return new CallableDataScopeAuthorizer(static fn (): bool => true);
    }

    return new CallableDataScopeAuthorizer($authorize);
}
