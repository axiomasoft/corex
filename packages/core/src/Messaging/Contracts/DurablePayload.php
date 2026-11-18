<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Тип пересекает durable-границу: очередь, outbox, чекпойнт, wakeup, bulk.
 *
 * @see RUN-002 RUN-003
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DurablePayload {}
