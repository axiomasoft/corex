<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
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
    public function resolve(Request $request, Principal $principal, InvocationMetadata $metadata): RequestContext
    {
        try {
            $tenant = $this->tenants->current();
        } catch (CentralContextException) {
            throw new ApiError(OperationsErrorCode::ContextRequired, 403, 'An initialized account context is required.');
        }

        if ($this->requirePhysicalTenancy && ($tenant->isBoxed() || ! $this->manager->initialized()
            || ($this->manager->context() <=> $tenant) !== 0)) {
            throw new ApiError(OperationsErrorCode::ContextRequired, 403, 'An initialized physical account context is required.');
        }

        if ($tenant->account->status !== 'active') {
            throw new ApiError(OperationsErrorCode::AccountUnavailable, 403, 'Account is unavailable.');
        }

        return new ResolvedContext($tenant, $principal, $metadata);
    }
}
