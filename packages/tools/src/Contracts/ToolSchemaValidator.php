<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Data\SchemaValidation;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolSchemaValidator
{
    public function admit(JsonObject $schema, string $profile): SchemaHandle;

    public function validate(SchemaHandle $schema, JsonValue $value): SchemaValidation;
}
