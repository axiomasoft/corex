<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

/**
 * Дедупликация идемпотентного потребителя (MSG-006).
 *
 * claim() вызывается ВНУТРИ той же транзакции, что бизнес-изменение, и возвращает
 * false на дубликате. Разделение отметки и эффекта на две транзакции ломает
 * дедупликацию в обе стороны: либо эффект теряется, либо применяется дважды
 * (research/P5-design.md §7.1).
 *
 * Отметка НЕ удаляется по умолчанию: её удаление заново открывает окно дублей (D29).
 *
 * Обязательная форма потребителя (research/P5-design.md §7.2):
 *
 *     $this->connection->transaction(function () use ($envelope): void {
 *         if (! $this->inbox->claim(
 *             consumer: 'search.projection.v1',
 *             messageId: $envelope->messageId,
 *             name: $envelope->name,
 *         )) {
 *             return; // дубликат: доставка подтверждается, бизнес-эффект не повторяется
 *         }
 *
 *         $this->apply($envelope);
 *     });
 *
 * Не подтверждать доставку брокеру до коммита локальной транзакции.
 */
interface Inbox
{
    /** @return bool true — заявка получена, эффект применять; false — дубликат */
    public function claim(string $consumer, string $messageId, string $name): bool;
}
