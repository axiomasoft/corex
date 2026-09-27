<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\LeasedMessage;
use Throwable;

/**
 * Операторская сторона outbox: захват аренды, отметка результата.
 *
 * lease() — короткая транзакция без единого сетевого вызова внутри. Публикация
 * выполняется ПОСЛЕ её коммита; markPublished() и releaseWithBackoff() выполняются
 * отдельными запросами с fence-предикатом по владельцу аренды.
 */
interface OutboxRelay
{
    /** @return list<LeasedMessage> */
    public function lease(string $leaseOwner, int $batchSize): array;

    public function markPublished(LeasedMessage $message): void;

    public function releaseWithBackoff(LeasedMessage $message, Throwable $error): void;
}
