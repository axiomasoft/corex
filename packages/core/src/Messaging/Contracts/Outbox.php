<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\MessageEnvelope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Запись намерения опубликовать сообщение.
 *
 * Вызывается ВНУТРИ уже открытой транзакции бизнес-операции: атомарность бизнес-
 * изменения и записи outbox — единственная причина, по которой этот механизм
 * существует. dispatch()->afterCommit() его не заменяет (MSG-008,
 * research/P5-design.md §4.1).
 *
 * Порядок доставки НЕ гарантируется (D28, research/P5-design.md §5.9).
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Outbox
{
    public function append(MessageEnvelope $message): void;
}
