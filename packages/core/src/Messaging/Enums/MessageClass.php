<?php

declare(strict_types=1);

namespace CoreX\Messaging\Enums;

use Packman\Attributes\Api;

/**
 * Три класса сообщений (MSG-001). Класс — объявление, а не тип PHP: событие
 * фреймворка не становится интеграционным контрактом от того, что на него
 * подписались (MSG-002).
 */
#[Api(since: '1.0')]
enum MessageClass: string
{
    /** Свершившийся факт внутри одного контекста; наружу не публикуется. */
    case DomainEvent = 'domain-event';

    /** Опубликованный факт и контракт совместимости. */
    case IntegrationEvent = 'integration-event';

    /** Намерение изменить состояние; ровно один владелец-обработчик. */
    case Command = 'command';
}
