<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use Illuminate\Http\Request;

interface PrincipalResolver
{
    /** Resolve only from trusted authentication; never from request body/query actor fields. */
    public function resolve(Request $request): ?Principal;
}
