<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Internal\ResolvedContext;
use CoreX\Operations\Invocation\InvocationMetadata;

/** Build the engine's request context through its public contract. */
final class ResolvedContexts
{
    public static function make(TenantContext $tenantContext, Principal $authenticatedPrincipal, InvocationMetadata $requestMetadata): RequestContext
    {
        return new ResolvedContext($tenantContext, $authenticatedPrincipal, $requestMetadata);
    }
}
