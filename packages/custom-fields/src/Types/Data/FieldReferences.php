<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final class FieldReferences
{
    public static function visible(DataScopeAuthorizer $access, DataScope $scope, FieldDefinition $field, FieldValue $value): bool
    {
        if (! in_array($field->type->value, ['reference', 'file'], true)) {
            return true;
        }
        $decoded = json_decode($value->document->canonicalJson, flags: JSON_THROW_ON_ERROR);
        foreach ($field->localizationPolicy === 'none' ? [$decoded] : (array) $decoded as $ref) {
            if ($ref === null) {
                continue;
            }

            if (! $ref instanceof stdClass || ! is_string($ref->entity ?? null) || ! is_string($ref->id ?? null)
                || ! $access->authorize(scope: $scope, record: new RecordRef(entity: $ref->entity, id: $ref->id), fields: new FieldSelection([]), use: FieldUse::Read)->allowed) {
                return false;
            }
        }

        return true;
    }
}
