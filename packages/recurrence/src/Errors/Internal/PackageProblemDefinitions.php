<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Errors\Internal;

use CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode;
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
        yield new ProblemDefinition(code: RecurrenceErrorCode::StaleCursorException, type: 'urn:packman:problem:recurrence.stale_cursor_exception', defaultStatus: 409, title: 'Stale cursor exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: RecurrenceErrorCode::UnsupportedEnvironmentException, type: 'urn:packman:problem:recurrence.unsupported_environment_exception', defaultStatus: 422, title: 'Unsupported environment exception.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
