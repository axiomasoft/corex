<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Internal;

use CoreX\Context\Contracts\Principal;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use Illuminate\Http\Request;
use Override;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
final class DenyPrincipalResolver implements PrincipalResolver
{
    #[Override]
    public function resolve(Request $request): ?Principal
    {
        return null;
    }
}
