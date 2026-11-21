<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ProjectionSnapshotCapabilities
{
    /** Whether describing and loading this projection can run in a read-only snapshot. */
    public function supportsReadOnlySnapshot(): bool;
}
