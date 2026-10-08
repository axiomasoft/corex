<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Invocation\Contracts\RequestContext;

/** Access for module-owned adapters invoked by an Action within the current operation. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RequestContextProvider
{
    public function current(): RequestContext;
}
