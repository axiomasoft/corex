<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\InvocationMetadata;
use Illuminate\Http\Request;

interface ContextResolver
{
    /** Consume initialized tenancy; optional workspace narrowing must verify this principal's membership. */
    public function resolve(Request $request, Principal $principal, InvocationMetadata $metadata): RequestContext;
}
