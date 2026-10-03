<?php

declare(strict_types=1);

namespace CoreX\Tools\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;

/** @internal Safe definitions contributed before the registry freezes. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: ToolsErrorCode::ToolUnavailable, type: 'urn:corex:problem:tools.tool_unavailable', defaultStatus: 404, title: 'Tool is unavailable.', retryable: false, category: ErrorCategory::NotFound);
        yield new ProblemDefinition(code: ToolsErrorCode::ToolRevisionConflict, type: 'urn:corex:problem:tools.tool_revision_conflict', defaultStatus: 409, title: 'Tool revision conflict.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: ToolsErrorCode::InputInvalid, type: 'urn:corex:problem:tools.input_invalid', defaultStatus: 422, title: 'Tool input is invalid.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: ToolsErrorCode::SchemaUnavailable, type: 'urn:corex:problem:tools.schema_unavailable', defaultStatus: 503, title: 'Tool schema is unavailable.', retryable: true, category: ErrorCategory::Unavailable);
        yield new ProblemDefinition(code: ToolsErrorCode::SchemaLimit, type: 'urn:corex:problem:tools.schema_limit', defaultStatus: 422, title: 'Tool schema limit exceeded.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: ToolsErrorCode::OutputContractFailed, type: 'urn:corex:problem:tools.output_contract_failed', defaultStatus: 500, title: 'Tool output contract failed.', retryable: false, category: ErrorCategory::Internal);
        yield new ProblemDefinition(code: ToolsErrorCode::RateLimited, type: 'urn:corex:problem:tools.rate_limited', defaultStatus: 429, title: 'Tool rate limit exceeded.', retryable: true, category: ErrorCategory::RateLimit);
        yield new ProblemDefinition(code: ToolsErrorCode::InternalError, type: 'urn:corex:problem:tools.internal_error', defaultStatus: 500, title: 'Internal tool error.', retryable: false, category: ErrorCategory::Internal);
        yield new ProblemDefinition(code: ToolsErrorCode::ApprovalRequired, type: 'urn:corex:problem:tools.approval_required', defaultStatus: 403, title: 'Tool approval is required.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: ToolsErrorCode::ApprovalInvalid, type: 'urn:corex:problem:tools.approval_invalid', defaultStatus: 403, title: 'Tool approval is invalid.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: ToolsErrorCode::Forbidden, type: 'urn:corex:problem:tools.forbidden', defaultStatus: 403, title: 'Tool invocation is forbidden.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: ToolsErrorCode::IdempotencyConflict, type: 'urn:corex:problem:tools.idempotency_conflict', defaultStatus: 409, title: 'Tool idempotency conflict.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: ToolsErrorCode::UnknownEffect, type: 'urn:corex:problem:tools.unknown_effect', defaultStatus: 500, title: 'Tool effect outcome is unknown.', retryable: false, category: ErrorCategory::Internal);
    }
}
