<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: RecurrenceErrorCode::StaleCursorException, type: 'urn:corex:problem:recurrence.stale_cursor_exception', defaultStatus: 409, title: 'Stale cursor exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: RecurrenceErrorCode::UnsupportedEnvironmentException, type: 'urn:corex:problem:recurrence.unsupported_environment_exception', defaultStatus: 422, title: 'Unsupported environment exception.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
