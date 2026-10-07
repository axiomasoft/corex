<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use InvalidArgumentException;

/** Count schema resources, depth and nodes against tool-schema/1 limits. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SchemaBudget
{
    /**
     * @param  array<array-key, mixed>  $schema
     * @return array<string, string>
     */
    public static function admit(array $schema, string $rawJson): array
    {
        if (strlen($rawJson) > SchemaProfile::MAX_SCHEMA_BYTES) {
            throw new InvalidArgumentException('schema_limit');
        }

        self::rejectForbiddenKeywords($schema);
        $resources = self::collectResources($schema);
        $nodeCount = self::countNodes($schema);
        $depth = self::maxDepth($schema);

        if (count($resources) > SchemaProfile::MAX_RESOURCES) {
            throw new InvalidArgumentException('schema_limit');
        }

        if ($nodeCount > SchemaProfile::MAX_NODES || $depth > SchemaProfile::MAX_DEPTH) {
            throw new InvalidArgumentException('schema_limit');
        }

        self::assertRegexBudget($schema);

        return $resources;
    }

    public static function assertPayload(string $rawJson, mixed $decoded): void
    {
        if (strlen($rawJson) > SchemaProfile::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('schema_limit');
        }

        if (self::countMembers($decoded) > SchemaProfile::MAX_PAYLOAD_MEMBERS
            || self::payloadDepth($decoded) > SchemaProfile::MAX_DEPTH) {
            throw new InvalidArgumentException('schema_limit');
        }
    }

    /**
     * @param  array<array-key, mixed>  $schema
     */
    private static function rejectForbiddenKeywords(array $schema): void
    {
        foreach ($schema as $key => $value) {
            if (in_array($key, SchemaProfile::REJECTED_KEYWORDS, true)) {
                throw new InvalidArgumentException('Unsupported schema keyword: '.$key);
            }

            if (is_array($value)) {
                self::rejectForbiddenKeywords($value);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @return array<string, string>
     */
    private static function collectResources(array $schema, string $prefix = '#'): array
    {
        $resources = [$prefix => JsonCanonical::encode($schema)];

        if (isset($schema['$defs']) && is_array($schema['$defs'])) {
            foreach ($schema['$defs'] as $name => $definition) {
                if (! is_string($name) || ! is_array($definition)) {
                    throw new InvalidArgumentException('Invalid local $defs entry.');
                }

                $resources['#/$defs/'.$name] = JsonCanonical::encode($definition);
                $resources += self::collectResources(schema: $definition, prefix: '#/$defs/'.$name);
            }
        }

        foreach ($schema as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                if (! str_starts_with($value, '#')) {
                    throw new InvalidArgumentException('Remote schema reference rejected.');
                }
            }

            if (is_array($value) && $key !== '$defs') {
                $resources += self::collectResources(schema: $value, prefix: $prefix);
            }
        }

        return $resources;
    }

    private static function countNodes(mixed $value): int
    {
        if (! is_array($value)) {
            return 1;
        }

        $count = 1;

        foreach ($value as $item) {
            $count += self::countNodes($item);
        }

        return $count;
    }

    private static function maxDepth(mixed $value, int $depth = 1): int
    {
        if (! is_array($value)) {
            return $depth;
        }

        $max = $depth;

        foreach ($value as $item) {
            $max = max($max, self::maxDepth(value: $item, depth: $depth + 1));
        }

        return $max;
    }

    private static function assertRegexBudget(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ($key === 'pattern' && is_string($item) && strlen($item) > SchemaProfile::MAX_REGEX_BYTES) {
                    throw new InvalidArgumentException('schema_limit');
                }

                self::assertRegexBudget($item);
            }
        }
    }

    private static function countMembers(mixed $value): int
    {
        if (! is_array($value)) {
            return 1;
        }

        $count = count($value);

        foreach ($value as $item) {
            $count += self::countMembers($item);
        }

        return $count;
    }

    private static function payloadDepth(mixed $value, int $depth = 1): int
    {
        if (! is_array($value)) {
            return $depth;
        }

        $max = $depth;

        foreach ($value as $item) {
            $max = max($max, self::payloadDepth(value: $item, depth: $depth + 1));
        }

        return $max;
    }
}
