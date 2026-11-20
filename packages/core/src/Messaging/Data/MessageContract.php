<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Internal\UriReference;
use Packman\Attributes\Api;

/**
 * Объявление опубликованного контракта сообщения.
 *
 * Существование этой записи — и есть повышение факта до публичного контракта
 * (MSG-002). Ни рефлексия, ни подписка, ни имя класса такого повышения не дают.
 */
#[Api(since: '1.0')]
final readonly class MessageContract
{
    /** Delivery class of a journaled broker fact; subscribers deduplicate by logical identity. */
    public const string RETRY_CLASS = 'durable-broker-handoff';

    public const string RETENTION = 'consumer-evidence';

    public string $handler;

    public function __construct(
        public string $name,
        public int $version,
        public MessageClass $class,
        public string $owner,
        public PiiClass $maxPiiClass,
        public string $channel,
        public ?string $dataSchema = null,
        public bool $derived = false,
        ?string $handler = null,
    ) {
        self::assertContractName($name);
        $this->handler = $handler ?? $channel;

        if ($handler !== null && ($handler === '' || strlen($handler) > 120 || preg_match('/[\x00-\x1f\x7f]/', $handler) === 1)) {
            throw InvalidMessage::identity('handler identity');
        }

        if ($dataSchema !== null && ! UriReference::isAbsolute($dataSchema)) {
            throw InvalidMessage::identity('contract data schema');
        }

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

    public function validateFact(MessageEnvelope $message): void
    {
        if ($message->contract() !== $this->id() || ! $this->maxPiiClass->permits($message->piiClass)
            || ($this->derived && $message->causationId === null)) {
            throw InvalidMessage::identity('declared contract, lineage or PII limit');
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
