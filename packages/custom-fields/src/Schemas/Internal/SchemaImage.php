<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;

/**
 * @phpstan-import-type Json8 from FieldInput
 * @phpstan-import-type FieldMetadata from FieldDefinition
 */
#[Internal]
final class SchemaImage
{
    /** @param array<string,mixed> $entries */
    public function __construct(public array $entries = []) {}

    public function definition(string $id): FieldDefinition
    {
        return self::hydrate(SchemaValue::map($this->entry($id)['definition']));
    }

    /** @param array<string,mixed> $data */
    public static function hydrate(array $data): FieldDefinition
    {
        $nullable = $data['nullable'];

        if (! is_bool($nullable)) {
            throw new SchemaConflict('invalid-definition-nullability');
        }

        return new FieldDefinition(id: new FieldId(SchemaValue::text($data['id'])), entity: new EntityHandle(SchemaValue::text($data['entity'])),
            machineKey: SchemaValue::text($data['machine_key']), type: new FieldTypeId(SchemaValue::text($data['type'])), owner: SchemaValue::text($data['owner']),
            codecVersion: SchemaValue::integer($data['codec_version']), revision: SchemaValue::integer($data['revision']), nullable: $nullable,
            constraints: self::metadata($data['constraints']), uses: array_map(FieldUse::from(...), SchemaValue::strings($data['uses'])),
            presentation: self::metadata($data['presentation']), indexPolicy: SchemaValue::text($data['index_policy']), localizationPolicy: SchemaValue::text($data['localization_policy']), lifecycle: SchemaValue::text($data['lifecycle']));
    }

    /** @return FieldMetadata */
    private static function metadata(mixed $value): array
    {
        $map = SchemaValue::map($value);
        $checked = [];
        foreach ($map as $key => $entry) {
            self::checkJson($entry);
            $checked[$key] = $entry;
        }

        return $checked;
    }

    /** @phpstan-assert Json8 $entry */
    private static function checkJson(mixed $entry, int $depth = 0): void
    {
        if (is_array($entry) && $depth < 8) {
            foreach ($entry as $value) {
                self::checkJson(entry: $value, depth: $depth + 1);
            }
        } elseif ($entry !== null && ! is_scalar($entry)) {
            throw new SchemaConflict('invalid-definition-metadata');
        }
    }

    /** @return array<string, mixed> */
    public function entry(string $id): array
    {
        return SchemaValue::map($this->entries[$id] ?? throw new SchemaConflict('field-missing'));
    }

    public function put(FieldDefinition $field, string $source, int $storageRevision, ?string $indexOperation = null): void
    {
        $this->entries[$field->id->value] = ['definition' => $field->toArray(), 'source' => $source,
            'slot' => $field->id->value.'@'.$storageRevision, 'storage_revision' => $storageRevision, 'index_operation' => $indexOperation];
        ksort($this->entries, SORT_STRING);
    }

    public function digest(): string
    {
        return SchemaRows::digest(['fields.mapping.v2', $this->entries]);
    }
}
