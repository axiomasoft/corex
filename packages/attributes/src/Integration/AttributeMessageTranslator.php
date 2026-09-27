<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Integration;

use CoreX\Contracts\DataScopeCodec;
use CoreX\Enums\PiiClass;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;

final readonly class AttributeMessageTranslator
{
    public function __construct(private DataScopeCodec $scopeCodec) {}

    public function translate(AttributeValuesChanged $event): MessageEnvelope
    {
        return new MessageEnvelope(
            messageId: $event->messageId,
            source: 'corex/attributes',
            name: AttributeMessageContracts::VALUES_CHANGED,
            contractVersion: 1,
            occurredAt: $event->occurredAt,
            scope: $this->scopeCodec->encode($event->scope),
            payload: new MessagePayload([
                'changedAttributeIds' => $event->changedAttributeIds,
                'changedKeys' => $event->changedKeys,
                'record' => [
                    'entity' => $event->recordEntity,
                    'id' => $event->recordId,
                ],
                'recordVersion' => $event->recordVersion,
                'schemaHash' => $event->schemaHash,
            ]),
            piiClass: PiiClass::Pseudonymous,
            correlationId: $event->correlationId,
            causationId: $event->causationId,
        );
    }
}
