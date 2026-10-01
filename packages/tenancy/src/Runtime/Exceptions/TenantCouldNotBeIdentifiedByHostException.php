<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use CoreX\Tenancy\Laravel\Resolvers\HostTenantResolver;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

/**
 * Thrown by {@see HostTenantResolver} — extends the
 * stancl base class (not an interface) so {@see
 * \Stancl\Tenancy\Middleware\IdentificationMiddleware::initializeTenancy()}
 * catches it in its `$onFail` handler.
 */
#[Api(since: '1.0')]
final class TenantCouldNotBeIdentifiedByHostException extends TenantCouldNotBeIdentifiedException implements DescribesError
{
    public function __construct(string $host)
    {
        $this->tenantCouldNotBeIdentified("by host {$host}");
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(TenancyErrorCode::TenantCouldNotBeIdentifiedByHostException, ErrorCategory::NotFound, retryable: false);
    }
}
