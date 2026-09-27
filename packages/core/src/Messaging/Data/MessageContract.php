<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Enums\MessageClass;
use CoreX\Enums\PiiClass;
use CoreX\Exceptions\InvalidMessage;

/**
 * Объявление опубликованного контракта сообщения.
 *
 * Существование этой записи — и есть повышение факта до публичного контракта
 * (MSG-002). Ни рефлексия, ни подписка, ни имя класса такого повышения не дают.
 */
final readonly class MessageContract
{
    public function __construct(
        public string $name,
        public int $version,
        public MessageClass $class,
        public string $owner,
        public PiiClass $maxPiiClass,
        public string $channel,
    ) {
        self::assertContractName($name);

        if ($version < 1) {
            throw InvalidMessage::identity("contract version [{$version}]");
        }

        if ($class === MessageClass::DomainEvent) {
            throw InvalidMessage::identity(
                "contract [{$name}]: a domain event is not a published contract (MSG-002)",
            );
        }

        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $owner) !== 1) {
            throw InvalidMessage::identity("contract owner [{$owner}]");
        }

        if ($channel === '' || strlen($channel) > 120 || preg_match('/[\x00-\x1f\x7f]/', $channel) === 1) {
            throw InvalidMessage::identity("contract channel [{$channel}]");
        }
    }

    /** Опубликованная идентичность контракта: единственная строка, видимая потребителю. */
    public function id(): string
    {
        return "{$this->name}.v{$this->version}";
    }

    private static function assertContractName(string $value): void
    {
        if (str_contains($value, '\\') || str_starts_with($value, 'CoreX')) {
            throw InvalidMessage::contractName($value);
        }

        if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3}\z/D', $value) !== 1) {
            throw InvalidMessage::contractName($value);
        }
    }
}
