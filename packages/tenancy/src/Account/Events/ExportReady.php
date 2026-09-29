<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Events;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseLifecycle;
use Override;

/**
 * Fired by {@see StanclTenantDatabaseLifecycle::export()} once the
 * `root_account_exports` row reaches `status=ready` (B-11 §7.3). Built with
 * an explicit {@see TenantContext} — see {@see AccountPurged}'s docblock
 * for why.
 */
final class ExportReady extends DomainEvent
{
    public readonly string $accountId;

    public function __construct(
        AccountRef $account,
        public readonly string $exportId,
    ) {
        parent::__construct(new TenantContext($account));
        $this->accountId = $account->id;
    }

    #[Override]
    public static function name(): string
    {
        return 'account.export_ready';
    }
}
