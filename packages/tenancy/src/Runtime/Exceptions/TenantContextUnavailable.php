<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tenancy\Errors\Enums\TenancyErrorCode;
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

    public static function missingDatabase(string $accountId): self
    {
        return new self("Account [{$accountId}] has no tenant database yet.");
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

    public static function runtimePoisoned(): self
    {
        return new self('The tenant runtime failed to restore its state and must be recreated before reuse.');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: TenancyErrorCode::ContextUnavailable, category: ErrorCategory::Unavailable, retryable: false);
    }
}
