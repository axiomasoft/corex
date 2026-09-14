<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\RequestMetadata;
use CoreX\Tenancy\TenantContext;
use Override;

/** @internal */
final readonly class ResolvedContext implements RequestContext
{
    public function __construct(
        private TenantContext $tenantContext,
        private Principal $authenticatedPrincipal,
        private RequestMetadata $requestMetadata,
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
    public function metadata(): RequestMetadata
    {
        return $this->requestMetadata;
    }
}
