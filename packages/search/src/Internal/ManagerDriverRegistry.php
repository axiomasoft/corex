<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use Closure;
use CoreX\Search\Contracts\SearchDriverRegistry;

final readonly class ManagerDriverRegistry implements SearchDriverRegistry
{
    public function __construct(private SearchManager $manager) {}

    public function register(string $name, Closure $factory): void
    {
        $this->manager->extend($name, $factory);
    }
}
