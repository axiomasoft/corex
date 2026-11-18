<?php

declare(strict_types=1);

namespace CoreX\Access\Errors\Internal;

use CoreX\Access\Errors\Enums\AccessErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;

/** @internal The owning package contributes its safe error catalogue at boot. */
#[Internal]
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: AccessErrorCode::ScopeCapabilityMismatch, type: 'urn:packman:problem:access.scope_capability_mismatch', defaultStatus: 422, title: 'Scope capability mismatch.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
