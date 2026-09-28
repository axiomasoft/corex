<?php

declare(strict_types=1);

namespace CoreX\Api\Testing;

use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\InvocationMetadata;
use CoreX\Tenancy\TenantContext;

/** Build the engine's request context through its public contract. */
final class ResolvedContexts
{
    public static function make(TenantContext $tenantContext, Principal $authenticatedPrincipal, InvocationMetadata $requestMetadata): RequestContext
    {
        return new ResolvedContext($tenantContext, $authenticatedPrincipal, $requestMetadata);
    }
}
