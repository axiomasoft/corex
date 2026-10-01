<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Contracts\Foundation\Application;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface SearchDriverRegistry
{
    /** @param Closure(Application): SearchProvider $factory */
    public function register(string $name, Closure $factory): void;
}
