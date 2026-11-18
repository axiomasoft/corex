<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Operations\Definition\Internal\ApiOperationValidator;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationDefinitionValidator
{
    public static function validate(Api $api): void
    {
        ApiOperationValidator::validate($api);
    }

    public static function validateRoutePair(Api $first, Api $second): void
    {
        ApiOperationValidator::validateRoutePair(first: $first, second: $second);
    }

    public static function assertJsonValue(mixed $value): void
    {
        ApiOperationValidator::assertJsonValue($value);
    }
}
