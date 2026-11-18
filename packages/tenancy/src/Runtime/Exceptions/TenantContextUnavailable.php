<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Exceptions;

use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use RuntimeException;

/**
 * The tenant runtime cannot enter, or no longer holds, the requested account
 * context. A job or request that hits this never runs on a previous database.
 */
#[Api(since: '1.0')]
final class TenantContextUnavailable extends RuntimeException implements DescribesError
{
    public static function missingAccount(string $accountId): self
    {
        return new self("Account [{$accountId}] does not exist — cannot enter its tenant context.");
    }

    public static function missingWorkspace(string $workspaceId): self
    {
        return new self("Workspace [{$workspaceId}] does not exist in the current account.");
    }

    public static function notInitialized(): self
    {
        return new self('No tenant context is initialized.');
    }

    public static function invalidPayload(): self
    {
        return new self('The queued tenant payload is malformed.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::ContextUnavailable, category: ErrorCategory::Unavailable, retryable: false);
    }
}
