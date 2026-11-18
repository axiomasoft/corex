<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Integration;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use Packman\Attributes\Internal;

#[Internal]
final readonly class AttributeMessageTranslator
{
    public function __construct(private DataScopeCodec $scopeCodec) {}

    public function translate(AttributeValuesChanged $event): MessageEnvelope
    {
        return new MessageEnvelope(
            messageId: $event->messageId,
            source: 'axiomasoft/corex-custom-fields',
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
