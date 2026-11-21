<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Contracts;

use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceExclusion;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Search\Index\Data\IndexTarget;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** The host controls the actual cluster credentials, writers and queued tasks.
 * Bind and tag the same owner, and list it in the physical lifecycle inventory.
 */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface IndexNamespaceExclusion extends ModuleReferences, ReferenceExclusion
{
    /** Verify this durable lease owns the exact UID and excludes conflicting queued work through transition. */
    public function verifyTarget(IndexTarget $target, OwnerLease $lease): void;
}
