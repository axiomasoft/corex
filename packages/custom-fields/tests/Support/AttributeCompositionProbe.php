<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Support;

use CoreX\CustomFields\Internal\Laravel\AttributeComposition;

final class AttributeCompositionProbe
{
    /** @param array<string, mixed> $config */
    public static function from(array $config): void
    {
        AttributeComposition::fromArray($config);
    }
}
