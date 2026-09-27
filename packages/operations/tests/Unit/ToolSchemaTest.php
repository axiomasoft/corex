<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Unit;

use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\JsonValue;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\SchemaProfile;
use CoreX\Api\Internal\ToolSchemaSupervisor;
use CoreX\Api\Tests\TestCase;
use InvalidArgumentException;
use RuntimeException;

final class ToolSchemaTest extends TestCase
{
    private ToolSchemaSupervisor $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ToolSchemaSupervisor;
    }

    private function objectSchema(array $extra = []): JsonObject
    {
        return JsonObject::fromArray([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'name' => ['type' => 'string'],
                ...$extra,
            ],
        ]);
    }

    public function test_admits_draft2020_12_schema_closure_offline(): void
    {
        $schema = JsonObject::fromArray([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            '$defs' => [
                'name' => ['type' => 'string'],
            ],
            'properties' => [
                'name' => ['$ref' => '#/$defs/name'],
            ],
        ]);

        $handle = $this->validator->admit($schema, SchemaProfile::TOOL_SCHEMA_V1);

        $this->assertNotSame('', $handle->digest);
        $this->assertArrayHasKey('#', $handle->localResources);
    }

    public function test_rejects_remote_refs_and_dynamic_keywords(): void
    {
        $remote = JsonObject::fromArray([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => ['id' => ['$ref' => 'https://example.com/schema.json']],
        ]);

        $this->expectException(RuntimeException::class);
        $this->validator->admit($remote, SchemaProfile::TOOL_SCHEMA_V1);
    }

    public function test_preserves_object_vs_array_and_safe_integers(): void
    {
        $this->assertSame('{}', JsonObject::fromArray([])->canonicalJson);
        $this->assertSame('[1,2]', JsonCanonical::encode([1, 2]));
        $this->assertSame('{"count":1}', JsonCanonical::encode(['count' => 1.0]));

        try {
            JsonCanonical::encode(['count' => 9007199254740992]);
            self::fail('Expected InvalidArgumentException for unsafe integer.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('JSON number outside safe integer range.', $exception->getMessage());
        }
    }

    public function test_rejects_duplicate_object_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JsonValue('{"a":1,"a":2}');
    }

    public function test_rejects_dynamic_schema_keywords(): void
    {
        $dynamic = JsonObject::fromArray([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            '$dynamicRef' => '#foo',
        ]);

        $this->expectException(RuntimeException::class);
        $this->validator->admit($dynamic, SchemaProfile::TOOL_SCHEMA_V1);
    }

    public function test_additional_properties_same_subschema_semantics(): void
    {
        $schema = JsonObject::fromArray([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'allOf' => [
                ['properties' => ['base' => ['type' => 'string']]],
            ],
            'properties' => [
                'base' => ['type' => 'string'],
            ],
        ]);

        $handle = $this->validator->admit($schema, SchemaProfile::TOOL_SCHEMA_V1);
        $invalid = new JsonValue('{"extra":true}');
        $validation = $this->validator->validate($handle, $invalid);

        $this->assertFalse($validation->valid);
    }

    public function test_resource_budget_exhaustion_rejects_before_handler(): void
    {
        $large = str_repeat('a', SchemaProfile::MAX_SCHEMA_BYTES + 1);
        $schema = new JsonObject('{"$schema":"https://json-schema.org/draft/2020-12/schema","type":"object","description":"'.$large.'"}');

        $this->expectException(RuntimeException::class);
        $this->validator->admit($schema, SchemaProfile::TOOL_SCHEMA_V1);
    }

    public function test_validates_instance_with_bounded_errors(): void
    {
        $handle = $this->validator->admit($this->objectSchema(), SchemaProfile::TOOL_SCHEMA_V1);
        $valid = new JsonValue('{"name":"ok"}');
        $invalid = new JsonValue('{"name":1}');

        $this->assertTrue($this->validator->validate($handle, $valid)->valid);
        $this->assertFalse($this->validator->validate($handle, $invalid)->valid);
    }
}
