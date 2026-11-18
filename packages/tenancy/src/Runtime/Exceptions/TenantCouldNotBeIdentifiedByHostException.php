<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Exceptions;

use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use RuntimeException;

/** Thrown when `Request::getHost()` does not identify exactly one reachable account. */
#[Api(since: '1.0')]
final class TenantCouldNotBeIdentifiedByHostException extends RuntimeException implements DescribesError
{
    public function __construct(string $host)
    {
        parent::__construct("Tenant could not be identified by host {$host}");
    }

    public static function forHost(string $host): self
    {
        return new self($host);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::TenantCouldNotBeIdentifiedByHostException, category: ErrorCategory::NotFound, retryable: false);
    }
}
