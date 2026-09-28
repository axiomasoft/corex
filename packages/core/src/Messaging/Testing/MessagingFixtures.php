<?php

declare(strict_types=1);

namespace CoreX\Messaging\Testing;

use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\NullTraceContextBridge;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Messaging\Internal\SqlOutbox;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

/** The same implementations, exposed through contracts for consumer tests. */
final class MessagingFixtures
{
    /** @param list<MessageContract> $contracts */
    public static function contracts(array $contracts): MessageContractRegistry
    {
        return new ArrayMessageContractRegistry(contracts: $contracts);
    }

    public static function inbox(ConnectionInterface $connection, ClockInterface $clock): Inbox
    {
        return new SqlInbox(connection: $connection, clock: $clock);
    }

    public static function outbox(ConnectionInterface $connection, MessageContractRegistry $contracts, ClockInterface $clock, ?TraceContextBridge $traces = null): Outbox
    {
        return new SqlOutbox(connection: $connection, contracts: $contracts, clock: $clock, traces: $traces ?? new NullTraceContextBridge);
    }
}
