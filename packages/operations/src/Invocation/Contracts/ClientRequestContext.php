<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ClientRequestContext extends RequestContext
{
    public function clientId(): string;
}
