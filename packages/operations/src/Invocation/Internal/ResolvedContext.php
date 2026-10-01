<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use Override;

/** @internal */
final readonly class ResolvedContext implements RequestContext
{
    public function __construct(
        private TenantContext $tenantContext,
        private Principal $authenticatedPrincipal,
        private InvocationMetadata $requestMetadata,
    ) {}

    #[Override]
    public function tenant(): TenantContext
    {
        return $this->tenantContext;
    }

    #[Override]
    public function principal(): Principal
    {
        return $this->authenticatedPrincipal;
    }

    #[Override]
    public function metadata(): InvocationMetadata
    {
        return $this->requestMetadata;
    }
}
