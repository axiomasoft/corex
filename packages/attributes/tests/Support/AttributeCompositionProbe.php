<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tests\Support;

use CoreX\Attributes\Internal\Laravel\AttributeComposition;

final class AttributeCompositionProbe
{
    /** @param array<string, mixed> $config */
    public static function from(array $config): void
    {
        AttributeComposition::fromArray($config);
    }
}
