<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Context\Contracts\Principal;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PrincipalResolver
{
    /** Resolve only from trusted authentication; never from request body/query actor fields. */
    public function resolve(Request $request): ?Principal;
}
