<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Events;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldValuesChanged
{
    public const string NAME = 'fields.values.changed';

    /** @param list<string> $fieldIds */
    public function __construct(public DataScope $scope, public RecordRef $subject, public array $fieldIds, public string $schemaRevision, public string $valueRevision) {}

    public function envelope(string $messageId, DateTimeImmutable $occurredAt, string $correlationId, string $causationId): MessageEnvelope
    {
        return new MessageEnvelope(messageId: $messageId, source: 'urn:corex:custom-fields', name: self::NAME, contractVersion: 2, occurredAt: $occurredAt,
            scope: $this->scope, subject: $this->subject, payload: new MessagePayload(['field_ids' => $this->fieldIds, 'schema_revision' => $this->schemaRevision, 'value_revision' => $this->valueRevision]),
            piiClass: PiiClass::Pseudonymous, correlationId: $correlationId, causationId: $causationId);
    }
}
