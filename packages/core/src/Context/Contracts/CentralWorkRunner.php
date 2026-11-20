<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use Closure;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface CentralWorkRunner
{
    /**
     * Enter the host's central system context with no business principal.
     *
     * @template T
     *
     * @param  Closure():T  $work
     * @return T
     */
    public function run(Closure $work): mixed;
}
