<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Schema;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Data\AttributeDefinition;
use CoreX\Attributes\Data\AttributeId;
use CoreX\Attributes\Data\AttributeLifecycle;
use CoreX\Attributes\Data\IndexPolicy;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\EntityField;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use RuntimeException;

final readonly class ModuleFieldCatalogue implements FieldCatalogue
{
    /** @param array<string, bool> $drivers */
    public function __construct(
        private EntityRegistry $entities,
        private ModuleRegistry $modules,
        private TenantContextRestorer $contexts,
        private array $drivers,
    ) {}

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        $context = $this->contexts->restore($scope);

        $active = array_filter(
            $this->entities->all($context),
            static fn ($definition): bool => $definition->handle === $entity,
        );

        if ($active === []) {
            throw new RuntimeException('The entity is not active for the current tenant.');
        }

        $definitions = [];
        foreach ($this->modules->compiled()->entityFields as $compiled) {
            $field = $compiled->extender;

            if ($field->entityHandle !== $entity || $field->kind !== 'scalar') {
                continue;
            }

            if ($compiled->sleeping && ! $this->modules->isActive($compiled->declaringModule, $context)) {
                continue;
            }

            $definitions[] = $this->definition($entity, $field);
        }

        $definitions = AttributeDefinition::canonicalList($definitions);

        return new FieldSnapshot(
            entity: $entity,
            schemaVersion: 'attributes/2',
            schemaHash: AttributeDefinition::schemaHash($entity, $definitions),
            activeFields: array_map(
                static fn (AttributeDefinition $definition): array => $definition->snapshotRow(),
                $definitions,
            ),
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
            $id = AttributeId::legacy($entity, $field->fieldHandle);
            $meta['legacy_identity'] = true;
        }

        unset($meta['attribute_id']);

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
