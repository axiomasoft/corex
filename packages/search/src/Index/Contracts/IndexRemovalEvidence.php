<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Contracts;

use CoreX\Search\Index\Data\IndexTarget;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface IndexRemovalEvidence
{
    /** True only for authenticated exact-namespace absence. Unavailable backends throw. */
    public function absent(IndexTarget $target): bool;
}
