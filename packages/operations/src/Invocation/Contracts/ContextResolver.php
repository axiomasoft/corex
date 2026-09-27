<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\InvocationMetadata;
use Illuminate\Http\Request;

interface ContextResolver
{
    /** Consume initialized tenancy; optional workspace narrowing must verify this principal's membership. */
    public function resolve(Request $request, Principal $principal, InvocationMetadata $metadata): RequestContext;
}
