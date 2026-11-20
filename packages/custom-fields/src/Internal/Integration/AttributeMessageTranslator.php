<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Integration;

use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use Packman\Attributes\Internal;

#[Internal]
final readonly class AttributeMessageTranslator
{
    public function translate(AttributeValuesChanged $event): MessageEnvelope
    {
        return new MessageEnvelope(
            messageId: $event->messageId,
            source: 'axiomasoft/corex-custom-fields',
            name: AttributeMessageContracts::VALUES_CHANGED,
            contractVersion: 1,
            occurredAt: $event->occurredAt,
            scope: $event->scope,
            subject: new RecordRef(entity: $event->recordEntity, id: $event->recordId),
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
