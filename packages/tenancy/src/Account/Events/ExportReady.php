<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Events;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Events\DomainEvent;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Fired by {@see CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle::export()} once the
 * `root_account_exports` row reaches `status=ready`. Built with
 * an explicit {@see TenantContext} — see {@see AccountPurged}'s docblock
 * for why.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ExportReady extends DomainEvent
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
