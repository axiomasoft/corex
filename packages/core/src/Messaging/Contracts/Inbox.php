<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Atomically acquire a logical subscriber receipt in its own business transaction. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Inbox
{
    /** @return bool true — заявка получена, эффект применять; false — дубликат */
    public function claim(ConnectionInterface $connection, ConsumerId $consumer, MessageEnvelope $message): bool;
}
