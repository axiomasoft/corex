<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Operations\Validation\Data\JsonObject;

/** Read projection of one compiled tool declaration. Handler refs stay internal. */
#[Api(since: '1.0')]
final readonly class ToolDescriptor
{
    public const string SCHEMA_PROFILE = 'tool-schema/1';

    public function __construct(public string $module, public string $code, public string $toolVersion, public string $wireName, public string $title, public string $description, public string $ability, public string $sideEffect, public string $idempotency, public JsonObject $inputSchema, public JsonObject $outputSchema, public string $schemaProfile, public string $contractHash, public ?string $operationRef, public JsonDocument $availability) {}

}
