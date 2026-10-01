<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Http\Request;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PrincipalResolver
{
    /** Resolve only from trusted authentication; never from request body/query actor fields. */
    public function resolve(Request $request): ?Principal;
}
