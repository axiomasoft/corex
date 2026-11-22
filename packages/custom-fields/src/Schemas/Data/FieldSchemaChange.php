<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use CoreX\Data\Data\EntityHandle;
use InvalidArgumentException;
use Packman\Attributes\Api;

/**
 * @phpstan-import-type DefinitionRow from FieldDefinition
 *
 * @phpstan-type ChangeRow array{entity:string,kind:string,field:string,definition:?DefinitionRow,machine_key:?string,converter_id:?string,converter_version:?int}
 */
#[Api(since: '2.0.0')]
final readonly class FieldSchemaChange
{
    private function __construct(public EntityHandle $entity, public string $kind, public FieldId $field, public ?FieldDefinition $definition = null, public ?string $machineKey = null, public ?string $converterId = null, public ?int $converterVersion = null) {}

    public static function add(FieldDefinition $definition): self
    {
        return new self(entity: $definition->entity, kind: 'add', field: $definition->id, definition: $definition);
    }

    public static function rename(EntityHandle $entity, FieldId $field, string $machineKey): self
    {
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $machineKey) !== 1) {
            throw new InvalidArgumentException('Invalid field key.');
        }

        return new self(entity: $entity, kind: 'rename', field: $field, machineKey: $machineKey);
    }

    public static function revise(FieldDefinition $definition, ?string $converterId = null, ?int $converterVersion = null): self
    {
        if (($converterId === null) !== ($converterVersion === null) || $converterId === '' || ($converterVersion !== null && $converterVersion < 1)) {
            throw new InvalidArgumentException('Invalid converter identity.');
        }

        return new self(entity: $definition->entity, kind: 'revise', field: $definition->id, definition: $definition, converterId: $converterId, converterVersion: $converterVersion);
    }

    public static function retire(EntityHandle $entity, FieldId $field): self
    {
        return new self(entity: $entity, kind: 'retire', field: $field);
    }

    /** @return ChangeRow */
    public function toArray(): array
    {
        return ['entity' => $this->entity->value, 'kind' => $this->kind, 'field' => $this->field->value, 'definition' => $this->definition?->toArray(),
            'machine_key' => $this->machineKey, 'converter_id' => $this->converterId, 'converter_version' => $this->converterVersion];
    }

    /** @param ChangeRow $row */
    public static function fromArray(array $row): self
    {
        $entity = new EntityHandle($row['entity']);
        $field = new FieldId($row['field']);
        $definition = $row['definition'] === null ? null : FieldDefinition::fromArray($row['definition']);

        if ($definition !== null && ($definition->id->value !== $field->value || $definition->entity->value !== $entity->value)) {
            throw new InvalidArgumentException('Mismatched field identity.');
        }

        return match ($row['kind']) {
            'add' => self::add($definition ?? throw new InvalidArgumentException('Missing definition.')),
            'revise' => self::revise(definition: $definition ?? throw new InvalidArgumentException('Missing definition.'), converterId: $row['converter_id'], converterVersion: $row['converter_version']),
            'rename' => self::rename(entity: $entity, field: $field, machineKey: $row['machine_key'] ?? throw new InvalidArgumentException('Missing key.')),
            'retire' => self::retire(entity: $entity, field: $field),
            default => throw new InvalidArgumentException('Unknown schema operation.'),
        };
    }
}
