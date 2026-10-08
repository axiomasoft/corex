<?php

declare(strict_types=1);

enum ToolsErrorCode: string
{
    case SchemaLimit = 'tools.schema_limit';
}
final class ToolSchemaFailure extends RuntimeException
{
    public function __construct(public readonly ToolsErrorCode $errorCode)
    {
        parent::__construct($errorCode->value);
    }
}

try {
    throw new ToolSchemaFailure(ToolsErrorCode::SchemaLimit);
} catch (ToolSchemaFailure $error) {
    if ($error->errorCode !== ToolsErrorCode::SchemaLimit) {
        throw new RuntimeException('Enum property mismatch');
    } echo 'ToolSchemaFailure loaded/caught: '.$error->errorCode->value.PHP_EOL;
}
