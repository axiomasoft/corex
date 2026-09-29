<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Internal;

use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use Illuminate\Http\Request;
use Override;

/** @internal */
final class DenyPrincipalResolver implements PrincipalResolver
{
    #[Override]
    public function resolve(Request $request): ?Principal
    {
        return null;
    }
}
