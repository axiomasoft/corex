<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Events\DomainEvent;
use DateTimeImmutable;
use Override;

/**
 * Fired after {@see CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle::startTrial()} has transitioned
 * `provisioning → trial` and mirrored `root_account_events` (B-11 §5.1).
 * Built with an explicit {@see TenantContext} (not the container-resolved
 * default `DomainEvent` falls back to) — the FSM mutates `root_accounts`
 * from the CENTRAL scope, no tenancy is initialized at the point this
 * fires.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class TrialStarted extends DomainEvent
{
    public readonly string $accountId;

    public function __construct(
        AccountRef $account,
        public readonly DateTimeImmutable $trialEndsAt,
    ) {
        parent::__construct(new TenantContext($account));
        $this->accountId = $account->id;
    }

    #[Override]
    public static function name(): string
    {
        return 'account.trial_started';
    }
}
