<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use Packman\Attributes\Api;

/**
 * @phpstan-type JsonLevel0 null|bool|int|string
 * @phpstan-type JsonLevel1 null|bool|int|string|array<array-key, JsonLevel0>
 * @phpstan-type JsonLevel2 null|bool|int|string|array<array-key, JsonLevel1>
 * @phpstan-type JsonLevel3 null|bool|int|string|array<array-key, JsonLevel2>
 * @phpstan-type JsonLevel4 null|bool|int|string|array<array-key, JsonLevel3>
 * @phpstan-type JsonLevel5 null|bool|int|string|array<array-key, JsonLevel4>
 * @phpstan-type JsonLevel6 null|bool|int|string|array<array-key, JsonLevel5>
 * @phpstan-type JsonLevel7 null|bool|int|string|array<array-key, JsonLevel6>
 * @phpstan-type JsonLevel8 null|bool|int|string|array<array-key, JsonLevel7>
 * @phpstan-type JsonLevel9 null|bool|int|string|array<array-key, JsonLevel8>
 * @phpstan-type JsonLevel10 null|bool|int|string|array<array-key, JsonLevel9>
 * @phpstan-type JsonLevel11 null|bool|int|string|array<array-key, JsonLevel10>
 * @phpstan-type JsonLevel12 null|bool|int|string|array<array-key, JsonLevel11>
 * @phpstan-type JsonLevel13 null|bool|int|string|array<array-key, JsonLevel12>
 * @phpstan-type JsonLevel14 null|bool|int|string|array<array-key, JsonLevel13>
 * @phpstan-type JsonLevel15 null|bool|int|string|array<array-key, JsonLevel14>
 * @phpstan-type JsonLevel16 null|bool|int|string|array<array-key, JsonLevel15>
 * @phpstan-type JsonMap array<string, JsonLevel16>
 */
#[Api(since: '1.0')]
final readonly class ValueBag
{
    /** @param JsonMap $values */
    public function __construct(public array $values)
    {
        Validation::jsonMap(value: $values, name: 'attribute values');
    }
}
