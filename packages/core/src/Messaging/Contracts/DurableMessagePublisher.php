<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Marks an outbox publisher whose acknowledgement guarantees durable handoff.
 * The host must register a scoped implementation. Lossy transports such as
 * Redis PUBLISH cannot acknowledge durable delivery.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DurableMessagePublisher extends MessagePublisher {}
