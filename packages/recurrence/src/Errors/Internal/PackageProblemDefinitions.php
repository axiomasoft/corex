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
        yield new ProblemDefinition(RecurrenceErrorCode::StaleCursorException, 'urn:corex:problem:recurrence.stale_cursor_exception', 409, 'Stale cursor exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(RecurrenceErrorCode::UnsupportedEnvironmentException, 'urn:corex:problem:recurrence.unsupported_environment_exception', 422, 'Unsupported environment exception.', false, ErrorCategory::Unsupported, []);
    }
}
