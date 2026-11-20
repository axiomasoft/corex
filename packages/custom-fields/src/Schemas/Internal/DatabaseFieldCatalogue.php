<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DatabaseFieldCatalogue implements FieldCatalogue
{
    public function __construct(private WorkScopeRunner $runner, private DatabaseManager $database, private DatabaseSchemaHeads $heads,
        private FieldUsePolicy $policy, private FieldTypes $types) {}

    public function snapshot(DataScope $scope, EntityHandle $entity): FieldSchemaSnapshot
    {
        return $this->runner->run($scope, function () use ($scope, $entity): FieldSchemaSnapshot {
            $db = $this->database->connection();

            return $db->transaction(function () use ($db, $scope, $entity): FieldSchemaSnapshot {
                $schema = $this->heads->effective($db, $scope, $entity)->schema;
                $fields = [];
                $operators = [];
                foreach ($schema->fields as $id => $field) {
                    $uses = [];
                    $resolved = $this->types->resolve($field->type, $field->codecVersion);
                    $type = $resolved->definition();
                    foreach ($field->uses as $use) {
                        if (! $field->permits($use, $type)) {
                            continue;
                        }
                        $grant = $this->policy->plan($scope, $entity, new FieldUseRequest(new FieldSelection([$id]), $use));

                        if ($grant->kind === FieldUsePlanKind::Unconditional || ($grant->kind === FieldUsePlanKind::ScopedPredicate && $grant->recordIds !== [])) {
                            $uses[] = $use->value;
                        }
                    }

                    if ($uses !== []) {
                        $data = $field->toArray();
                        $data['uses'] = $uses;
                        $fields[$id] = SchemaImage::hydrate($data);

                        if (in_array(FieldUse::Filter->value, $uses, true)) {
                            $operators[$id] = $resolved->operators($field);
                        }
                    }
                }

                return new FieldSchemaSnapshot($entity, $schema->revision, $schema->mappingDigest, $fields, $operators);
            });
        });
    }
}
