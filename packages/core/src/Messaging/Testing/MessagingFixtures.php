<?php

declare(strict_types=1);

namespace CoreX\Messaging\Testing;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\NullTraceContextBridge;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Messaging\Internal\SqlOutbox;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Psr\Clock\ClockInterface;

/** The same implementations, exposed through contracts for consumer tests. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MessagingFixtures
{
    /** @param list<MessageContract> $contracts */
    public static function contracts(array $contracts): MessageContractRegistry
    {
        return new ArrayMessageContractRegistry(contracts: $contracts);
    }

    public static function inbox(CurrentWorkScope $current, MessageContractRegistry $contracts, ClockInterface $clock): Inbox
    {
        return new SqlInbox(current: $current, contracts: $contracts, clock: $clock);
    }

    public static function outbox(CurrentWorkScope $current, StoredWorkIssuer $issuer, MessageContractRegistry $contracts, ClockInterface $clock, DataScopeCodec $scopes, ?TraceContextBridge $traces = null): Outbox
    {
        return new SqlOutbox(scopes: $scopes, current: $current, issuer: $issuer, contracts: $contracts, clock: $clock, traces: $traces ?? new NullTraceContextBridge);
    }
}
