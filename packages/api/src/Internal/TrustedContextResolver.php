<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\ContextResolver;
use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\RequestMetadata;
use CoreX\Exceptions\CentralContextException;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Http\Request;
use Override;

/** @internal Reads trusted state afresh; holds no per-account or per-principal cache. */
final class TrustedContextResolver implements ContextResolver
{
    public function __construct(
        private readonly TenantContextResolver $tenants,
        private readonly TenancyManager $manager,
        private readonly bool $requirePhysicalTenancy = true,
    ) {}

    #[Override]
    public function resolve(Request $request, Principal $principal, RequestMetadata $metadata): RequestContext
    {
        try {
            $tenant = $this->tenants->current();
        } catch (CentralContextException) {
            throw new ApiError('context_required', 403, 'An initialized account context is required.');
        }

        if ($this->requirePhysicalTenancy && ($tenant->isBoxed() || ! $this->manager->initialized()
            || $this->manager->context() != $tenant)) {
            throw new ApiError('context_required', 403, 'An initialized physical account context is required.');
        }

        if ($tenant->account->status !== 'active') {
            throw new ApiError('account_unavailable', 403, 'Account is unavailable.');
        }

        return new ResolvedContext($tenant, $principal, $metadata);
    }
}
