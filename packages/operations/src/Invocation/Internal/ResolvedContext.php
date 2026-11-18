<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Data\TenantContext;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use Override;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
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
