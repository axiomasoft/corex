<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Opt-in for pre-action `DomainEvent`s a listener can abort (Architecture_CoreX
 * §7.3, e.g. a `ProductCreating`-style hook). Kept separate from `DomainEvent`
 * itself — most domain events are informational and never cancellable.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Cancellable
{
    public function cancel(): void;

    public function isCancelled(): bool;
}
