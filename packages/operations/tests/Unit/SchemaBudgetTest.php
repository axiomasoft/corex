<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Validation\Laravel\SchemaBudget;
use CoreX\Operations\Validation\Laravel\SchemaProfile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SchemaBudgetTest extends TestCase
{
    public function test_admits_small_object_schema(): void
    {
        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => ['name' => ['type' => 'string']],
        ];
        $raw = '{"$schema":"https://json-schema.org/draft/2020-12/schema","type":"object","additionalProperties":false,"properties":{"name":{"type":"string"}}}';

        $resources = SchemaBudget::admit($schema, $raw);

        $this->assertArrayHasKey('#', $resources);
    }

    public function test_rejects_remote_ref_in_budget_pass(): void
    {
        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => ['id' => ['$ref' => 'https://example.com/schema.json']],
        ];
        $raw = json_encode($schema, JSON_THROW_ON_ERROR);

        $this->expectException(InvalidArgumentException::class);
        SchemaBudget::admit($schema, $raw);
    }

    public function test_assert_payload_rejects_oversized_raw_json(): void
    {
        $raw = '"'.str_repeat('a', SchemaProfile::MAX_PAYLOAD_BYTES + 1).'"';

        $this->expectException(InvalidArgumentException::class);
        SchemaBudget::assertPayload($raw, str_repeat('a', SchemaProfile::MAX_PAYLOAD_BYTES + 1));
    }
}
