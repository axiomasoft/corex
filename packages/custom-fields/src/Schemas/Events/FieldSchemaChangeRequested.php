<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Events;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldSchemaChangeRequested
{
    public const string NAME = 'fields.schema.requested';

    public function __construct(public DataScope $scope, public EntityHandle $subject, public string $plan, public string $digest) {}

    public function envelope(string $messageId, DateTimeImmutable $occurredAt, string $correlationId, string $causationId): MessageEnvelope
    {
        return new MessageEnvelope(messageId: $messageId, source: 'urn:corex:custom-fields', name: self::NAME, contractVersion: 2, occurredAt: $occurredAt,
            scope: $this->scope, subject: $this->subject, payload: new MessagePayload(['plan_ref' => $this->plan, 'plan_digest' => $this->digest]),
            piiClass: PiiClass::Pseudonymous, correlationId: $correlationId, causationId: $causationId);
    }
}
