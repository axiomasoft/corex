<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use Closure;
use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ManagerDriverRegistry implements SearchDriverRegistry
{
    public function __construct(private SearchManager $manager) {}

    public function register(string $name, Closure $factory): void
    {
        // Laravel 13.0 binds the custom creator to the manager instance.
        $this->manager->extend($name, fn (Application $app): SearchProvider => $factory($app));
    }
}
