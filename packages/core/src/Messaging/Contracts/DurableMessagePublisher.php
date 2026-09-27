<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

/**
 * Маркер durable-ack реализации для outbox relay (D99).
 *
 * Core не связывает lossy PubSub/null/Redis PUBLISH с outbox. Хост обязан
 * зарегистрировать `scoped`-реализацию, реализующую этот интерфейс.
 */
interface DurableMessagePublisher extends MessagePublisher {}
