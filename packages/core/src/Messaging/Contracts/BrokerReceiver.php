<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Closure;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\BrokerReceipt;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface BrokerReceiver
{
    /** @param Closure(ConnectionInterface,MessageEnvelope):void $apply Resolve the subscriber inside this callback. */
    public function consume(ConsumerId $consumer, string $restrictedRef, Closure $apply): BrokerReceipt;
}
