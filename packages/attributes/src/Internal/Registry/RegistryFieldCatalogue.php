<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Registry;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Support\FieldSnapshot;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\EntityField;
use CoreX\Support\Data\DataScope;
use RuntimeException;

final readonly class RegistryFieldCatalogue implements FieldCatalogue
{
    /** @param array<string, bool> $drivers */
    public function __construct(
        private EntityRegistry $entities,
        private ModuleRegistry $modules,
        private array $drivers,
    ) {}

    public function snapshot(DataScope $scope, string $entity): FieldSnapshot
    {
        $active = array_filter(
            $this->entities->all($scope->context),
            static fn ($definition): bool => $definition->handle === $entity,
        );

        if ($active === []) {
            throw new RuntimeException('The entity is not active for the current tenant.');
        }

        $fields = [];
        foreach ($this->modules->compiled()->entityFields as $compiled) {
            $field = $compiled->extender;

            if ($field->entityHandle !== $entity || $field->kind !== 'scalar') {
                continue;
            }

            if ($compiled->sleeping && ! $this->modules->isActive($compiled->declaringModule, $scope->context)) {
                continue;
            }

            $fields[] = [
                'code' => $field->fieldHandle,
                'type' => $this->fieldType($field),
                'required' => $field->isRequired,
                'searchable' => $field->isSearchable,
                'meta' => $field->meta,
            ];
        }

        usort($fields, static fn (array $left, array $right): int => $left['code'] <=> $right['code']);
        $payload = json_encode($fields, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $entity."\n".$payload);

        return new FieldSnapshot(
            entity: $entity,
            schemaVersion: $hash,
            schemaHash: $hash,
            activeFields: $fields,
            driverCapabilities: $this->drivers,
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
