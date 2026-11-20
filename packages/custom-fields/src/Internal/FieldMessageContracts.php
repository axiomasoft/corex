<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\CustomFields\Schemas\Events\FieldSchemaChanged;
use CoreX\CustomFields\Schemas\Events\FieldSchemaChangeRequested;
use CoreX\CustomFields\Values\Events\FieldValuesChanged;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use Packman\Attributes\Internal;

#[Internal]
final class FieldMessageContracts
{
    /** @return list<MessageContract> */
    public static function all(): array
    {
        return array_map(static fn (string $name): MessageContract => new MessageContract(name: $name, version: 2,
            class: MessageClass::IntegrationEvent, owner: 'axiomasoft/corex-custom-fields', maxPiiClass: PiiClass::Pseudonymous,
            channel: 'fields', dataSchema: 'urn:corex:schema:'.$name.'.v2', derived: true),
            [FieldSchemaChangeRequested::NAME, FieldSchemaChanged::NAME, FieldValuesChanged::NAME]);
    }
}
