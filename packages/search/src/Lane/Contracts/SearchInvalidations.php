<?php

declare(strict_types=1);

namespace CoreX\Search\Lane\Contracts;

use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Data\MessageEnvelope;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface SearchInvalidations
{
    /** @param list<RecordRef> $records Apply on the open BrokerReceiver subscriber transaction. */
    public function apply(ConnectionInterface $writer, MessageEnvelope $event, EntityHandle $entity, array $records): void;
}
