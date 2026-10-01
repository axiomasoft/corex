<?php

declare(strict_types=1);

namespace CoreX\Intl\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Intl\Errors\Enums\IntlErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(IntlErrorCode::AccessDeniedException, 'urn:corex:problem:intl.access_denied_exception', 403, 'Access denied exception.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(IntlErrorCode::InvalidLocaleException, 'urn:corex:problem:intl.invalid_locale_exception', 422, 'Invalid locale exception.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(IntlErrorCode::InvalidRateException, 'urn:corex:problem:intl.invalid_rate_exception', 422, 'Invalid rate exception.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(IntlErrorCode::OperationConflictException, 'urn:corex:problem:intl.operation_conflict_exception', 409, 'Operation conflict exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(IntlErrorCode::SchemaMismatchException, 'urn:corex:problem:intl.schema_mismatch_exception', 409, 'Schema mismatch exception.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(IntlErrorCode::SnapshotExpiredException, 'urn:corex:problem:intl.snapshot_expired_exception', 404, 'Snapshot expired exception.', false, ErrorCategory::NotFound, []);
        yield new ProblemDefinition(IntlErrorCode::UnsupportedFormattingException, 'urn:corex:problem:intl.unsupported_formatting_exception', 422, 'Unsupported formatting exception.', false, ErrorCategory::Unsupported, []);
    }
}
