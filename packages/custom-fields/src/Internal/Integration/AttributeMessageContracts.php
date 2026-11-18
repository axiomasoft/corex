<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Integration;

use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use Packman\Attributes\Internal;

#[Internal]
final class AttributeMessageContracts
{
    public const string VALUES_CHANGED = 'attributes.values.changed';

    /** @return list<MessageContract> */
    public static function all(): array
    {
        return [
            new MessageContract(
                name: self::VALUES_CHANGED,
                version: 1,
                class: MessageClass::IntegrationEvent,
                owner: 'axiomasoft/corex-custom-fields',
                maxPiiClass: PiiClass::Pseudonymous,
                channel: 'attributes',
            ),
        ];
    }
}
