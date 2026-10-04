<?php

declare(strict_types=1);

namespace CoreX\Tools\Errors\Enums;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;

/** Registered errors owned by the tool runtime and its execution guards. */
#[Api(since: '1.0')]
enum ToolsErrorCode: string implements ErrorCode
{
    case ToolUnavailable = 'tools.tool_unavailable';

    case ToolRevisionConflict = 'tools.tool_revision_conflict';

    case InputInvalid = 'tools.input_invalid';

    case SchemaUnavailable = 'tools.schema_unavailable';

    case SchemaLimit = 'tools.schema_limit';

    case OutputContractFailed = 'tools.output_contract_failed';

    case RateLimited = 'tools.rate_limited';

    case InternalError = 'tools.internal_error';

    case ApprovalRequired = 'tools.approval_required';

    case ApprovalInvalid = 'tools.approval_invalid';

    case Forbidden = 'tools.forbidden';

    case IdempotencyConflict = 'tools.idempotency_conflict';

    case UnknownEffect = 'tools.unknown_effect';

    public function value(): string
    {
        return $this->value;
    }
}
