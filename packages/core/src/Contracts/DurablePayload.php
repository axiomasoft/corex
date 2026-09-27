<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Тип пересекает durable-границу: очередь, outbox, чекпойнт, wakeup, bulk.
 *
 * @see RUN-002 RUN-003
 */
interface DurablePayload {}
