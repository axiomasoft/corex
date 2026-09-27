<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Integration;

use CoreX\Enums\MessageClass;
use CoreX\Enums\PiiClass;
use CoreX\Messaging\Data\MessageContract;

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
                owner: 'corex/attributes',
                maxPiiClass: PiiClass::Pseudonymous,
                channel: 'attributes',
            ),
        ];
    }
}
