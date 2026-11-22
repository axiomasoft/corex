<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Schemas\Contracts\FieldProjectionCatalogue;
use CoreX\CustomFields\Schemas\Data\FieldProjectionSchema;
use CoreX\CustomFields\Schemas\Data\FieldProjectionShape;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DatabaseFieldProjectionCatalogue implements FieldProjectionCatalogue
{
    public function __construct(private WorkScopeRunner $runner, private DatabaseManager $database, private DatabaseSchemaHeads $heads, private FieldTypes $types) {}

    public function snapshot(DataScope $scope, EntityHandle $entity): FieldProjectionSchema
    {
        return $this->runner->run(scope: $scope, work: function () use ($scope, $entity): FieldProjectionSchema {
            $db = $this->database->connection();

            return $db->transaction(function () use ($db, $scope, $entity): FieldProjectionSchema {
                $mapping = $this->heads->effective(db: $db, scope: $scope, entity: $entity);
                $fields = [];
                foreach ($mapping->schema->fields as $id => $field) {
                    if ($field->lifecycle !== 'active' || ! isset($mapping->slots[$id])) {
                        continue;
                    }

                    $resolved = $this->types->resolve(type: $field->type, version: $field->codecVersion);
                    $descriptor = $resolved->definition();
                    $uses = array_values(array_filter($field->uses, static fn (FieldUse $use): bool => $field->permits($use, $descriptor)));
                    $operators = in_array(FieldUse::Filter, $uses, true) ? $resolved->operators($field) : null;
                    $fields[$id] = new FieldProjectionShape(id: $field->id, type: $field->type, uses: $uses, operators: $operators, localized: $field->localizationPolicy !== 'none');
                }

                return new FieldProjectionSchema(revision: $mapping->schema->revision, mappingDigest: $mapping->schema->mappingDigest, fields: $fields);
            });
        });
    }
}
