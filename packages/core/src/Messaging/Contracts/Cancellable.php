<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Opt-in for pre-action `DomainEvent`s a listener can abort (such as a `ProductCreating` hook). Kept separate from `DomainEvent`
 * itself — most domain events are informational and never cancellable.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Cancellable
{
    public function cancel(): void;

    public function isCancelled(): bool;
}
