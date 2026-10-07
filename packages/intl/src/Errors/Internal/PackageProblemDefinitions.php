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
        yield new ProblemDefinition(code: IntlErrorCode::AccessDeniedException, type: 'urn:corex:problem:intl.access_denied_exception', defaultStatus: 403, title: 'Access denied exception.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::InvalidLocaleException, type: 'urn:corex:problem:intl.invalid_locale_exception', defaultStatus: 422, title: 'Invalid locale exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::InvalidRateException, type: 'urn:corex:problem:intl.invalid_rate_exception', defaultStatus: 422, title: 'Invalid rate exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::OperationConflictException, type: 'urn:corex:problem:intl.operation_conflict_exception', defaultStatus: 409, title: 'Operation conflict exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::SchemaMismatchException, type: 'urn:corex:problem:intl.schema_mismatch_exception', defaultStatus: 409, title: 'Schema mismatch exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::SnapshotExpiredException, type: 'urn:corex:problem:intl.snapshot_expired_exception', defaultStatus: 404, title: 'Snapshot expired exception.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::UnsupportedFormattingException, type: 'urn:corex:problem:intl.unsupported_formatting_exception', defaultStatus: 422, title: 'Unsupported formatting exception.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
