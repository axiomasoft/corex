<?php

declare(strict_types=1);

enum ToolsErrorCode: string
{
    case SchemaLimit = 'tools.schema_limit';
}
final class ToolSchemaFailure extends RuntimeException
{
    public readonly ToolsErrorCode $code;
}
