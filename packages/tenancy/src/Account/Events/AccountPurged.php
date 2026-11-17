<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Fired by {@see CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle::drop()} once the account's
 * physical owner has confirmed deletion — the
 * `AccountLifecycle::purge()` caller does the `root_accounts` soft-delete
 * and `root_domains` cleanup, not this event's source. Built with an
 * explicit {@see TenantContext} — no tenancy is initialized at drop time
 * (the operation uses the central account identity).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class AccountPurged extends DomainEvent
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
