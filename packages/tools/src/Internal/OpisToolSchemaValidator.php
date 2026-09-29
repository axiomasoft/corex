<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Data\SchemaValidation;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Operations\Validation\SchemaBudget;
use CoreX\Operations\Validation\SchemaProfile;
use InvalidArgumentException;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;

/** Qualified Opis adapter for offline tool-schema/1 validation. */
final class OpisToolSchemaValidator
{
    public static function isAvailable(): bool
    {
        return class_exists(Validator::class);
    }

    public function admit(JsonObject $schema, string $profile): SchemaHandle
    {
        if ($profile !== SchemaProfile::TOOL_SCHEMA_V1) {
            throw new InvalidArgumentException('Unsupported schema profile: '.$profile);
        }

        if (! self::isAvailable()) {
            throw new RuntimeException('schema_unavailable');
        }

        $decoded = JsonCanonical::decode($schema->canonicalJson);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Tool schema root must be an object.');
        }

        $resources = SchemaBudget::admit($decoded, $schema->canonicalJson);
        $digest = hash('sha256', $schema->canonicalJson.'|'.$profile);

        return new SchemaHandle(
            digest: $digest,
            profile: $profile,
            localResources: $resources,
        );
    }

    public function validate(SchemaHandle $schema, JsonValue $value): SchemaValidation
    {
        if (! self::isAvailable()) {
            throw new RuntimeException('schema_unavailable');
        }

        $decoded = JsonCanonical::decode($value->canonicalJson);
        SchemaBudget::assertPayload($value->canonicalJson, $decoded);

        $rootSchema = json_decode(
            $schema->localResources['#'] ?? throw new InvalidArgumentException('Missing admitted schema root.'),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        $validator = new Validator(max_errors: SchemaProfile::MAX_ERRORS, stop_at_first_error: false);
        $result = $validator->validate(json_decode($value->canonicalJson, false, 512, JSON_THROW_ON_ERROR), $rootSchema);

        if ($result->isValid()) {
            return new SchemaValidation(valid: true, errors: []);
        }

        return new SchemaValidation(
            valid: false,
            errors: self::collectErrors($result->error()),
        );
    }

    /**
     * @return list<array{code: string, pointer: string}>
     */
    private static function collectErrors(?ValidationError $error): array
    {
        if ($error === null) {
            return [];
        }

        $errors = [];
        $queue = [$error];

        while ($queue !== [] && count($errors) < SchemaProfile::MAX_ERRORS) {
            $current = array_shift($queue);
            $errors[] = [
                'code' => $current->keyword(),
                'pointer' => self::pointer($current->data()->fullPath()),
            ];

            foreach ($current->subErrors() as $subError) {
                $queue[] = $subError;
            }
        }

        return $errors;
    }

    /**
     * @param  list<int|string>  $segments
     */
    private static function pointer(array $segments): string
    {
        if ($segments === []) {
            return '';
        }

        $parts = array_map(static fn (int|string $segment): string => str_replace('~', '~0', str_replace('/', '~1', (string) $segment)), $segments);

        return '/'.implode('/', $parts);
    }
}
