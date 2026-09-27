<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\MessageEnvelope;

/**
 * Назначение публикации арендованного сообщения.
 *
 * Выполняется ВНЕ транзакции БД. Успешный return подтверждает durable handoff,
 * переживающий offline consumer (D99). Реализация идемпотентна по messageId или
 * терпит повтор; окно дублей остаётся at-least-once.
 *
 * Wire-массив, который durable-реализации передают потребителю (P10, P14), содержит
 * ровно двенадцать ключей в объявленном порядке; `contract` — строка `name.vN`,
 * `occurredAt` — RFC 3339 с долями секунды. Перестановка ключей — ломающее изменение,
 * а не форматирование (как `ArrayDataScopeCodec`, P4.8). Канал публикации берётся из
 * объявления контракта в реестре, а не из полей конверта.
 */
interface MessagePublisher
{
    public function publish(MessageEnvelope $message): void;
}
