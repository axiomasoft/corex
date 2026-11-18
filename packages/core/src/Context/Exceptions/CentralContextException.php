<?php

declare(strict_types=1);

namespace CoreX\Context\Exceptions;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

/**
 * Thrown by {@see TenantContextResolver::current()}
 * cloud implementations when called outside an initialized tenant (root/
 * central scope) — a caller that needs a tenant context asked for one where
 * none exists, e.g. Settings resolution reached from a control-plane
 * request. The boxed `SingleAccountResolver` never throws this (constant
 * context, always "initialized").
 */
#[Api(since: '1.0')]
final class CentralContextException extends CodedException
{
    public function __construct()
    {
        parent::__construct('No tenant context is active — this call must run inside an initialized tenant (not the central/root scope).');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::CentralContextException, category: ErrorCategory::Authorization, retryable: false);
    }
}
