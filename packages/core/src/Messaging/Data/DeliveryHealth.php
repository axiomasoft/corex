<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Attributes\Api;

/**
 * Здоровье доставки: вход для таксономии наблюдаемости P12.
 *
 * Возраст старейшего ожидающего идёт первым намеренно: глубина измеряет накопленную
 * работу, возраст — нарушенное обещание доставки (research/P5-design.md §9.1).
 *
 * Частота дублей здесь отсутствует: её нельзя прочитать из таблиц, она считается
 * потребителем по возврату Inbox::claim() === false.
 */
#[Api(since: '1.0')]
final readonly class DeliveryHealth
{
    /** @param array<string, int> $oldestPendingSecondsByContract */
    public function __construct(
        public ?int $oldestPendingSeconds,
        public array $oldestPendingSecondsByContract,
        public int $pendingCount,
        public int $publishingCount,
        public int $deadCount,
        public int $maxAttempts,
    ) {}
}
