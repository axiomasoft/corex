<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\Api;
use CoreX\Operations\Definition\Internal\ApiOperationValidator;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationDefinitionValidator
{
    public static function validate(Api $api): void
    {
        ApiOperationValidator::validate($api);
    }

    public static function validateRoutePair(Api $first, Api $second): void
    {
        ApiOperationValidator::validateRoutePair($first, $second);
    }

    public static function assertJsonValue(mixed $value): void
    {
        ApiOperationValidator::assertJsonValue($value);
    }
}
