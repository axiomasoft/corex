<?php

declare(strict_types=1);

namespace CoreX\Tools\Errors\Internal;

use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;

/** @internal Safe definitions contributed before the registry freezes. */
#[Internal]
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: ToolsErrorCode::ToolUnavailable, type: 'urn:packman:problem:tools.tool_unavailable', defaultStatus: 404, title: 'Tool is unavailable.', retryable: false, category: ErrorCategory::NotFound);
        yield new ProblemDefinition(code: ToolsErrorCode::ToolRevisionConflict, type: 'urn:packman:problem:tools.tool_revision_conflict', defaultStatus: 409, title: 'Tool revision conflict.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: ToolsErrorCode::InputInvalid, type: 'urn:packman:problem:tools.input_invalid', defaultStatus: 422, title: 'Tool input is invalid.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: ToolsErrorCode::SchemaUnavailable, type: 'urn:packman:problem:tools.schema_unavailable', defaultStatus: 503, title: 'Tool schema is unavailable.', retryable: true, category: ErrorCategory::Unavailable);
        yield new ProblemDefinition(code: ToolsErrorCode::SchemaLimit, type: 'urn:packman:problem:tools.schema_limit', defaultStatus: 422, title: 'Tool schema limit exceeded.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: ToolsErrorCode::OutputContractFailed, type: 'urn:packman:problem:tools.output_contract_failed', defaultStatus: 500, title: 'Tool output contract failed.', retryable: false, category: ErrorCategory::Internal);
        yield new ProblemDefinition(code: ToolsErrorCode::RateLimited, type: 'urn:packman:problem:tools.rate_limited', defaultStatus: 429, title: 'Tool rate limit exceeded.', retryable: true, category: ErrorCategory::RateLimit);
        yield new ProblemDefinition(code: ToolsErrorCode::InternalError, type: 'urn:packman:problem:tools.internal_error', defaultStatus: 500, title: 'Internal tool error.', retryable: false, category: ErrorCategory::Internal);
        yield new ProblemDefinition(code: ToolsErrorCode::ApprovalRequired, type: 'urn:packman:problem:tools.approval_required', defaultStatus: 403, title: 'Tool approval is required.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: ToolsErrorCode::ApprovalInvalid, type: 'urn:packman:problem:tools.approval_invalid', defaultStatus: 403, title: 'Tool approval is invalid.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: ToolsErrorCode::Forbidden, type: 'urn:packman:problem:tools.forbidden', defaultStatus: 403, title: 'Tool invocation is forbidden.', retryable: false, category: ErrorCategory::Authorization);
        yield new ProblemDefinition(code: ToolsErrorCode::IdempotencyConflict, type: 'urn:packman:problem:tools.idempotency_conflict', defaultStatus: 409, title: 'Tool idempotency conflict.', retryable: false, category: ErrorCategory::Conflict);
        yield new ProblemDefinition(code: ToolsErrorCode::UnknownEffect, type: 'urn:packman:problem:tools.unknown_effect', defaultStatus: 500, title: 'Tool effect outcome is unknown.', retryable: false, category: ErrorCategory::Internal);
    }
}
