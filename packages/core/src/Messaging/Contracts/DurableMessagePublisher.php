<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * Маркер durable-ack реализации для outbox relay (D99).
 *
 * Core не связывает lossy PubSub/null/Redis PUBLISH с outbox. Хост обязан
 * зарегистрировать `scoped`-реализацию, реализующую этот интерфейс.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DurableMessagePublisher extends MessagePublisher {}
