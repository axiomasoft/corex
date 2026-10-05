<?php

declare(strict_types=1);

namespace CoreX\Context\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Context\Enums\ImpersonationRestrictedAction;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

/**
 * Thrown by any of the three read-only-invariant layers (D141) when an
 * impersonated session attempts a write the layer does not recognize as
 * explicitly allowed. Named constructors point at the exact escape hatch
 * for the layer that raised it — a bare "write blocked" message would leave
 * the premortem A8/A9 diagnosis (route marker vs `allowing()` scope) to
 * guesswork.
 */
#[Api(since: '1.0')]
final class ImpersonationRestrictedException extends CodedException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /** L2 — `CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard` (axiomasoft/corex-tenancy; named, not `{@see}`-linked — core must not import it, D91). */
    public static function forModelWrite(string $modelClass): self
    {
        return new self(
            "Model write blocked under impersonation: {$modelClass}. ".
            'Use the current scoped ImpersonatedModelWriteGuard instance allowing() method for an intentional mutation.',
        );
    }

    /** L3 — {@see CoreX\Context\Internal\ImpersonationGuard::assertAllowed()}. */
    public static function forAction(ImpersonationRestrictedAction $action): self
    {
        return new self(
            "Action restricted under impersonation: {$action->name} (B-11 §5.9 п.3).",
        );
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::ImpersonationRestrictedException, ErrorCategory::Authorization, retryable: false);
    }
}
