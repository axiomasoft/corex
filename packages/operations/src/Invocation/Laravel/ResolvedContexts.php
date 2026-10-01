<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Internal\ResolvedContext;

/** Build the engine's request context through its public contract. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ResolvedContexts
{
    public static function make(TenantContext $tenantContext, Principal $authenticatedPrincipal, InvocationMetadata $requestMetadata): RequestContext
    {
        return new ResolvedContext($tenantContext, $authenticatedPrincipal, $requestMetadata);
    }
}
