<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Operations\Validation\Laravel\SchemaProfile;
use CoreX\Tools\Internal\OpisToolSchemaValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OpisToolSchemaValidatorTest extends TestCase
{
    public function test_offline_adapter_accepts_valid_values_and_reports_nested_error_pointers(): void
    {
        $validator = new OpisToolSchemaValidator;
        $handle = $validator->admit(JsonObjectFactory::fromArray([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['items'],
            'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'integer']]],
        ]), SchemaProfile::TOOL_SCHEMA_V1);

        self::assertSame(SchemaProfile::TOOL_SCHEMA_V1, $handle->profile);
        $valid = $validator->validate($handle, new JsonValue('{"items":[1,2]}'));
        self::assertTrue($valid->valid);
        self::assertSame([], $valid->errors);
        $invalid = $validator->validate($handle, new JsonValue('{"items":["bad"]}'));
        self::assertFalse($invalid->valid);
        self::assertContains('/items/0', array_column($invalid->errors, 'pointer'));
        self::assertContains('type', array_column($invalid->errors, 'code'));
    }

    public function test_numeric_schema_extension_keywords_and_property_names_remain_valid(): void
    {
        $validator = new OpisToolSchemaValidator;
        $schema = new JsonObject('{"12":"annotation","type":"object","properties":{"12":{"type":"integer"}},"required":["12"]}');
        $handle = $validator->admit($schema, SchemaProfile::TOOL_SCHEMA_V1);

        self::assertTrue($validator->validate($handle, new JsonValue('{"12":1}'))->valid);
        self::assertFalse($validator->validate($handle, new JsonValue('{"12":"bad"}'))->valid);
    }

    public function test_adapter_rejects_unsupported_profiles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported schema profile');
        (new OpisToolSchemaValidator)->admit(JsonObjectFactory::fromArray(['type' => 'object']), 'unknown');
    }

    public function test_validation_requires_an_admitted_root(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing admitted schema root');
        (new OpisToolSchemaValidator)->validate(new SchemaHandle('digest', SchemaProfile::TOOL_SCHEMA_V1, []), new JsonValue('{}'));
    }
}
