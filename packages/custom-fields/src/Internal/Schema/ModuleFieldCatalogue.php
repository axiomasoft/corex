<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Internal\Support\Validation;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Entities\EntityField;
use RuntimeException;

#[Internal]
final readonly class ModuleFieldCatalogue implements FieldCatalogue
{
    /** @param array<string, bool> $drivers */
    public function __construct(
        private EntityRegistry $entities,
        private ModuleRegistry $modules,
        private CurrentWorkScope $contexts,
        private array $drivers,
    ) {}

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        $context = $this->contexts->assert($scope);

        $active = array_filter(
            $this->entities->all($context),
            static fn ($definition): bool => $definition->handle === $entity,
        );

        if ($active === []) {
            throw new InactiveEntity('The entity is not active for the current tenant.');
        }

        $definitions = [];
        foreach ($this->modules->compiled()->entityFields as $compiled) {
            $field = $compiled->extender;

            if ($field->entityHandle !== $entity || $field->kind !== 'scalar') {
                continue;
            }

            if ($compiled->sleeping && ! $this->modules->isActive(name: $compiled->declaringModule, ctx: $context)) {
                continue;
            }

            $definitions[] = $this->definition(entity: $entity, field: $field);
        }

        $definitions = AttributeDefinition::canonicalList($definitions);

        return new FieldSnapshot(
            entity: $entity,
            schemaVersion: 'attributes/2',
            schemaHash: AttributeDefinition::schemaHash(entity: $entity, definitions: $definitions),
            activeFields: Validation::validatedMapList(values: array_map(
                static fn (AttributeDefinition $definition): array => $definition->snapshotRow(),
                $definitions,
            ), name: 'active fields'),
            driverCapabilities: $this->drivers,
        );
    }

    private function definition(string $entity, EntityField $field): AttributeDefinition
    {
        $meta = $field->meta;
        $pinned = $meta['attribute_id'] ?? null;

        if (is_string($pinned) && $pinned !== '') {
            $id = AttributeId::v7($pinned);
        } else {
            $id = AttributeId::legacy(entity: $entity, key: $field->fieldHandle);
            $meta['legacy_identity'] = true;
        }

        unset($meta['attribute_id']);

        $meta = Validation::validatedMap(value: $meta, name: 'attribute metadata');

        return new AttributeDefinition(
            id: $id,
            entity: $entity,
            key: $field->fieldHandle,
            revision: 1,
            type: $this->fieldType($field),
            lifecycle: AttributeLifecycle::Active,
            required: $field->isRequired,
            searchable: $field->isSearchable,
            constraints: [],
            readKeys: [],
            writeKeys: [],
            index: IndexPolicy::None,
            meta: $meta,
        );
    }

    private function fieldType(EntityField $field): string
    {
        $type = $field->type ?? throw new RuntimeException('Scalar entity field has no type.');

        return match ($type) {
            'string' => 'text',
            'int', 'decimal', 'bool', 'date', 'datetime', 'select', 'multiselect', 'reference', 'money', 'url', 'email', 'phone', 'file', 'json', 'text' => $type,
            default => throw new RuntimeException("Unsupported attribute field type [{$type}]."),
        };
    }
}
