<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

/** Counts how often the container built it: a scoped instance must be rebuilt after every switch. */
final class ScopedProbe
{
    public static int $built = 0;

    public readonly int $generation;

    public function __construct()
    {
        $this->generation = ++self::$built;
    }
}
