<?php

declare(strict_types=1);

namespace CoreX\Invocation\Contracts;

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Data\TenantContext;
use CoreX\Invocation\Data\InvocationMetadata;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Trusted invocation context handed to an OperationHandler, separate from untrusted input. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RequestContext
{
    public function tenant(): TenantContext;

    public function principal(): Principal;

    public function metadata(): InvocationMetadata;
}
