<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Data\SchemaValidation;

interface ToolSchemaValidator
{
    public function admit(JsonObject $schema, string $profile): SchemaHandle;

    public function validate(SchemaHandle $schema, JsonValue $value): SchemaValidation;
}
