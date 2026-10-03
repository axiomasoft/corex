<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Data\MessageEnvelope;

/**
 * Назначение публикации арендованного сообщения.
 *
 * Выполняется ВНЕ транзакции БД. Успешный return подтверждает durable handoff,
 * переживающий offline consumer (D99). Реализация идемпотентна по messageId или
 * терпит повтор; окно дублей остаётся at-least-once.
 *
 * Публикует байты CloudEventCodec::encode() — CloudEvents 1.0 structured JSON
 * с media type application/cloudevents+json. Отображение — message-policy.md.
 * Канал публикации берётся из объявления контракта в реестре.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface MessagePublisher
{
    public function publish(MessageEnvelope $message): void;
}
