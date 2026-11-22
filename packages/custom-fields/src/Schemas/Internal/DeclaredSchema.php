<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Enums\Entities\Capability;

#[Internal]
final readonly class DeclaredSchema
{
    /** @param Closure():iterable<DeclaredFields> $declarations */
    public function __construct(private FieldTypes $types, private Closure $declarations, private ?EntityRegistry $entities, private ?ModuleRegistry $modules) {}

    public function image(TenantContext $context, EntityHandle $entity): SchemaImage
    {
        $image = new SchemaImage;
        $active = $this->entities === null ? null : array_find($this->entities->byCapability(Capability::CustomFields, $context),
            static fn ($candidate): bool => $candidate->handle === $entity->value);

        if ($this->entities !== null && $active === null) {
            throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        }
        $known = array_column($this->modules?->all() ?? [], 'composerName');
        foreach (($this->declarations)() as $source) {
            if (in_array($source->owner(), $known, true) && $this->modules?->isActive($source->owner(), $context) !== true) {
                continue;
            }
            foreach ($source->fields($entity) as $field) {
                $this->add(image: $image, entity: $entity, owner: $source->owner(), field: $field);
            }
        }
        foreach ($this->modules?->compiled()->entityFields ?? [] as $compiled) {
            $field = $compiled->extender;

            if ($field->entityHandle !== $entity->value || $field->kind !== 'scalar' || $this->modules?->isActive($compiled->declaringModule, $context) !== true) {
                continue;
            }
            $this->add(image: $image, entity: $entity, owner: $compiled->declaringModule, field: new FieldDefinition(
                id: new FieldId(SchemaValue::text($field->meta['field_id'] ?? $field->meta['attribute_id'] ?? null)), entity: $entity, machineKey: $field->fieldHandle,
                type: new FieldTypeId($field->type ?? throw new SchemaConflict('missing-field-type')), owner: $compiled->declaringModule,
                codecVersion: SchemaValue::integer($field->meta['codec_version'] ?? 1), nullable: ! $field->isRequired, uses: [FieldUse::Read, FieldUse::Write],
            ));
        }

        if ($active === null && $image->entries === []) {
            throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        }

        return $image;
    }

    private function add(SchemaImage $image, EntityHandle $entity, string $owner, FieldDefinition $field): void
    {
        if ($field->entity->value !== $entity->value || $field->owner !== $owner || $field->lifecycle !== 'active'
            || isset($image->entries[$field->id->value])) {
            throw new SchemaConflict('conflicting-declared-field');
        }
        foreach (array_keys($image->entries) as $id) {
            if ($image->definition($id)->machineKey === $field->machineKey) {
                throw new SchemaConflict('conflicting-declared-machine-key');
            }
        }
        $this->types->resolve(type: $field->type, version: $field->codecVersion);
        $image->put(field: $field, source: 'code', storageRevision: $field->revision);
    }
}
