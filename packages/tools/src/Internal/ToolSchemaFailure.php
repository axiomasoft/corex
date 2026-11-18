<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Packman\Attributes\Internal;
use RuntimeException;

/** @internal Typed schema failure, independent of diagnostic exception text. */
#[Internal]
final class ToolSchemaFailure extends RuntimeException
{
    public function __construct(public readonly ToolsErrorCode $errorCode)
    {
        parent::__construct(message: $errorCode->value);
    }

    public static function unavailable(): self
    {
        return new self(errorCode: ToolsErrorCode::SchemaUnavailable);
    }

    public static function limit(): self
    {
        return new self(errorCode: ToolsErrorCode::SchemaLimit);
    }
}
