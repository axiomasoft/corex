<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Events;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Tenancy\Provisioning\Internal\StanclTenantDatabaseLifecycle;
use Override;

/**
 * Fired by {@see StanclTenantDatabaseLifecycle::drop()} once the account's
 * physical database has been dropped (B-11 §5.1/§7.3, D13/AC-7) — the
 * `AccountLifecycle::purge()` caller does the `root_accounts` soft-delete
 * and `root_domains` cleanup, not this event's source. Built with an
 * explicit {@see TenantContext} — no tenancy is initialized at drop time
 * (central-scope `DROP DATABASE`).
 */
final class AccountPurged extends DomainEvent
{
    public readonly string $accountId;

    public function __construct(AccountRef $account)
    {
        parent::__construct(new TenantContext($account));
        $this->accountId = $account->id;
    }

    #[Override]
    public static function name(): string
    {
        return 'account.purged';
    }
}
