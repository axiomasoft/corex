<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\MessageEnvelope;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Records a publication in the same transaction as the business change (MSG-008).
 * The business change and outbox row must commit together. A dispatch via
 * `afterCommit()` cannot make that guarantee. Delivery order is unspecified.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Outbox
{
    public function append(ConnectionInterface $connection, MessageEnvelope $message): void;
}
