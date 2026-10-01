<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Exceptions\UnknownMessageContract;

/**
 * Реестр объявленных контрактов сообщений (MSG-002).
 *
 * Запись в этом реестре — единственный способ сделать факт публикуемым. Ни подписка,
 * ни базовый класс события, ни рефлексия публикации не дают.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface MessageContractRegistry
{
    public function has(string $name, int $version): bool;

    /** @throws UnknownMessageContract */
    public function get(string $name, int $version): MessageContract;

    /** @return list<MessageContract> отсортирован по id() */
    public function all(): array;
}
