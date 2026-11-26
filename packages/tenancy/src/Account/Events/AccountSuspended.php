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
 * Fired after {@see CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle::suspend()} has transitioned
 * `active → suspended` and mirrored `root_account_events`. Built
 * with an explicit {@see TenantContext} — see {@see TrialStarted}'s
 * docblock for why.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class AccountSuspended extends DomainEvent
{
    public readonly string $accountId;

    public function __construct(
        AccountRef $account,
        public readonly string $reason,
    ) {
        parent::__construct(new TenantContext($account));
        $this->accountId = $account->id;
    }

    #[Override]
    public static function name(): string
    {
        return 'account.suspended';
    }
}
