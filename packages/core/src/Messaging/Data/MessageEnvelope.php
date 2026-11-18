<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Messaging\Contracts\DurablePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Internal\UriReference;
use DateTimeImmutable;
use Packman\Attributes\Api;

/**
 * Конверт опубликованного сообщения (MSG-003).
 *
 * `envelopeVersion` — версия формата ЭТОГО конверта; `contractVersion` — мажорная
 * версия схемы содержимого. Их смешение — типовая и дорогая ошибка, поэтому это два
 * независимых свойства (см. research/P5-design.md §2.2).
 *
 * Наружу публикуется одна идентичность контракта — contract(), то есть `name.vN`:
 * подписка на имя без версии однажды получит следующую мажорную версию (D24).
 */
#[Api(since: '1.0')]
final readonly class MessageEnvelope implements DurablePayload
{
    public const int ENVELOPE_VERSION = 1;

    /**
     * @param  array<string, scalar|null>  $scope  результат DataScopeCodec::encode()
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $source,
        public string $name,
        public int $contractVersion,
        public DateTimeImmutable $occurredAt,
        public array $scope,
        public MessagePayload $payload,
        public PiiClass $piiClass,
        public ?string $subject = null,
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public array $metadata = [],
        public int $envelopeVersion = self::ENVELOPE_VERSION,
    ) {
        self::assertOpaque(value: $messageId, name: 'message identity');
        self::assertOpaque(value: $source, name: 'message source');

        if (! UriReference::isValid($source)) {
            throw InvalidMessage::identity('message source');
        }

        if ($contractVersion < 1) {
            throw InvalidMessage::identity("contract version [{$contractVersion}]");
        }

        if ($envelopeVersion !== self::ENVELOPE_VERSION) {
            throw InvalidMessage::identity("envelope version [{$envelopeVersion}]");
        }

        if ($scope === []) {
            throw InvalidMessage::identity('scope: a published message carries a data scope (RUN-002)');
        }

        if ($subject !== null) {
            self::assertOpaque(value: $subject, name: 'message subject');
        }

        if ($correlationId !== null) {
            self::assertOpaque(value: $correlationId, name: 'correlation identity');
        }

        if ($causationId !== null) {
            self::assertOpaque(value: $causationId, name: 'causation identity');
        }
    }

    /** Опубликованная идентичность контракта: `membership.expired.v1`. */
    public function contract(): string
    {
        return "{$this->name}.v{$this->contractVersion}";
    }

    public function withPayload(MessagePayload $payload): self
    {
        return new self(
            messageId: $this->messageId, source: $this->source, name: $this->name,
            contractVersion: $this->contractVersion, occurredAt: $this->occurredAt,
            scope: $this->scope, payload: $payload, piiClass: $this->piiClass,
            subject: $this->subject, correlationId: $this->correlationId,
            causationId: $this->causationId, metadata: $this->metadata,
            envelopeVersion: $this->envelopeVersion,
        );
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw InvalidMessage::identity($name);
        }
    }
}
