<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Context\Data\StoredWorkProof;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Contracts\DurablePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Internal\UriReference;
use DateTimeImmutable;
use DateTimeZone;
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
#[Api(since: '2.0.0')]
final readonly class MessageEnvelope implements DurablePayload
{
    public const int ENVELOPE_VERSION = 2;

    public const string PROOF_OWNER = 'corex.messaging.outbox';

    public DateTimeImmutable $occurredAt;

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $source,
        public string $name,
        public int $contractVersion,
        DateTimeImmutable $occurredAt,
        public DataScope $scope,
        public MessagePayload $payload,
        public PiiClass $piiClass,
        public RecordRef|EntityHandle $subject,
        public string $correlationId,
        public ?string $causationId = null,
        public array $metadata = [],
        public int $envelopeVersion = self::ENVELOPE_VERSION,
        public ?string $seal = null,
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

        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        self::assertOpaque(value: $correlationId, name: 'correlation identity');

        if ($seal !== null && preg_match('/\A[a-f0-9]{64}\z/D', $seal) !== 1) {
            throw InvalidMessage::identity('provenance seal');
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

    /** @return array{entity:string,id?:string} */
    public function subjectData(): array
    {
        return $this->subject instanceof RecordRef
            ? ['entity' => $this->subject->entity, 'id' => $this->subject->id]
            : ['entity' => $this->subject->value];
    }

    public function digest(): string
    {
        return hash('sha256', json_encode([
            'corex.fact.v2', $this->contract(), $this->messageId, $this->source, $this->occurredAt->format('Y-m-d\\TH:i:s.u\\Z'),
            [DataScope::VERSION, $this->scope->deploymentId, $this->scope->accountId, $this->scope->workspaceId, $this->scope->bindingId,
                $this->scope->bindingGeneration, $this->scope->accountIncarnation, $this->scope->restoreEpoch, $this->scope->principalKind, $this->scope->principalId], $this->subjectData(), $this->correlationId, $this->causationId,
            $this->piiClass->value, $this->payload->encode(),
        ], JSON_THROW_ON_ERROR));
    }

    public function workProof(): StoredWorkProof
    {
        return new StoredWorkProof(self::PROOF_OWNER, $this->digest(), $this->seal ?? throw InvalidMessage::identity('missing stored work provenance'));
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw InvalidMessage::identity($name);
        }
    }
}
