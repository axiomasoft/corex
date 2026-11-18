<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface SearchDriverRegistry
{
    /** @param Closure(Application): SearchProvider $factory */
    public function register(string $name, Closure $factory): void;
}
