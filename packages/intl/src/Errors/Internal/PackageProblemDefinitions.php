<?php

declare(strict_types=1);

namespace CoreX\Intl\Errors\Internal;

use CoreX\Intl\Errors\Enums\IntlErrorCode;
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
        yield new ProblemDefinition(code: IntlErrorCode::AccessDeniedException, type: 'urn:packman:problem:intl.access_denied_exception', defaultStatus: 403, title: 'Access denied exception.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::InvalidLocaleException, type: 'urn:packman:problem:intl.invalid_locale_exception', defaultStatus: 422, title: 'Invalid locale exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::InvalidRateException, type: 'urn:packman:problem:intl.invalid_rate_exception', defaultStatus: 422, title: 'Invalid rate exception.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::OperationConflictException, type: 'urn:packman:problem:intl.operation_conflict_exception', defaultStatus: 409, title: 'Operation conflict exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::SchemaMismatchException, type: 'urn:packman:problem:intl.schema_mismatch_exception', defaultStatus: 409, title: 'Schema mismatch exception.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::SnapshotExpiredException, type: 'urn:packman:problem:intl.snapshot_expired_exception', defaultStatus: 404, title: 'Snapshot expired exception.', retryable: false, category: ErrorCategory::NotFound, detailKeys: []);
        yield new ProblemDefinition(code: IntlErrorCode::UnsupportedFormattingException, type: 'urn:packman:problem:intl.unsupported_formatting_exception', defaultStatus: 422, title: 'Unsupported formatting exception.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
