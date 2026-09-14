<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use Illuminate\Http\Request;

interface PrincipalResolver
{
    /** Resolve only from trusted authentication; never from request body/query actor fields. */
    public function resolve(Request $request): ?Principal;
}
