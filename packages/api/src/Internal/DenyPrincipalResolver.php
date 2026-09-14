<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\PrincipalResolver;
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
