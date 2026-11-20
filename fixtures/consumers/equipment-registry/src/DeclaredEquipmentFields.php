<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;

final class DeclaredEquipmentFields implements DeclaredFields
{
    public const string Serial = '01900000-0000-7000-8000-000000000001';

    public const string Hours = '01900000-0000-7000-8000-000000000002';

    public function owner(): string
    {
        return 'equipment/registry';
    }

    /** @return list<FieldDefinition> */
    public function fields(EntityHandle $entity): array
    {
        if ($entity->value !== Equipment::Handle) {
            return [];
        }

        $uses = [FieldUse::Read, FieldUse::Write, FieldUse::Filter];

        return [
            new FieldDefinition(new FieldId(self::Serial), $entity, 'serial', new FieldTypeId('text'), $this->owner(), uses: $uses),
            new FieldDefinition(new FieldId(self::Hours), $entity, 'hours', new FieldTypeId('int'), $this->owner(), uses: $uses),
        ];
    }
}
