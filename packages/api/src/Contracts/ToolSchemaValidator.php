<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\JsonValue;
use CoreX\Api\Data\SchemaHandle;
use CoreX\Api\Data\SchemaValidation;

interface ToolSchemaValidator
{
    public function admit(JsonObject $schema, string $profile): SchemaHandle;

    public function validate(SchemaHandle $schema, JsonValue $value): SchemaValidation;
}
