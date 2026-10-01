<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\Data\InvocationMetadata;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RequestContext
{
    public function tenant(): TenantContext;

    public function principal(): Principal;

    public function metadata(): InvocationMetadata;
}
