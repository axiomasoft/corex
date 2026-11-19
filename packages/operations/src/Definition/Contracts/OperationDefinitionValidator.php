<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Invocation\OperationDefinitionValidator as InvocationValidator;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationDefinitionValidator
{
    public static function validate(Api $api): void
    {
        InvocationValidator::validate($api);
    }

    public static function validateRoutePair(Api $first, Api $second): void
    {
        InvocationValidator::validateRoutePair(first: $first, second: $second);
    }

    public static function assertJsonValue(mixed $value): void
    {
        InvocationValidator::assertJsonValue($value);
    }
}
