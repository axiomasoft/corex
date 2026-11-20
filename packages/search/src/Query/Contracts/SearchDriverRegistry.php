<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Contracts;

use Closure;
use CoreX\Search\Index\Contracts\IndexDriver;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface SearchDriverRegistry
{
    /** @param Closure(Application):CandidateDriver $factory */
    public function register(string $name, Closure $factory): void;

    /** @param Closure(Application):IndexDriver $factory */
    public function registerIndex(string $name, Closure $factory): void;
}
