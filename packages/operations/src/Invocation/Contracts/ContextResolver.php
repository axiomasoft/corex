<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Context\Contracts\Principal;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ContextResolver
{
    /** Consume initialized tenancy; optional workspace narrowing must verify this principal's membership. */
    public function resolve(Request $request, Principal $principal, InvocationMetadata $metadata): RequestContext;
}
