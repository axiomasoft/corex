<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Support;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\SchemaHeads;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Schemas\Data\LockedFieldMapping;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;

final readonly class ValueFixture implements FieldCatalogue, FieldUsePolicy, SchemaHeads
{
    public FieldDefinition $field;

    public FieldSchemaSnapshot $schema;

    public function __construct(string $type = 'text', array $constraints = [], string $localization = 'none')
    {
        $id = new FieldId('01900000-0000-7000-8000-000000000001');
        $entity = new EntityHandle('cas.equipment');
        $this->field = new FieldDefinition($id, $entity, 'serial', new FieldTypeId($type), 'runtime', constraints: $constraints,
            uses: [FieldUse::Read, FieldUse::Write, FieldUse::Filter], localizationPolicy: $localization);
        $extraId = new FieldId('01900000-0000-7000-8000-000000000002');
        $extra = new FieldDefinition($extraId, $entity, 'secondary', new FieldTypeId('text'), 'runtime', uses: [FieldUse::Read, FieldUse::Write]);
        $this->schema = new FieldSchemaSnapshot($entity, new SchemaRevision(str_repeat('b', 64)), str_repeat('c', 64), [$id->value => $this->field, $extraId->value => $extra]);
    }

    public function bind(Application $app): void
    {
        $runner = jsonbCurrent();
        foreach ([CurrentWorkScope::class, StoredWorkIssuer::class, WorkScopeRunner::class] as $port) {
            $app->instance($port, $runner);
        }
        foreach ([FieldCatalogue::class, SchemaHeads::class, FieldUsePolicy::class] as $port) {
            $app->instance($port, $this);
        }
        $app->instance(DataScopeAuthorizer::class, jsonbAuthorizer());
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        return FieldUsePlan::unconditional('test-policy-1');
    }

    public function snapshot(DataScope $scope, EntityHandle $entity): FieldSchemaSnapshot
    {
        return $this->schema;
    }

    public function lockForWrite(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): LockedFieldMapping
    {
        return $this->effective($db, $scope, $entity);
    }

    public function effective(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): LockedFieldMapping
    {
        return new LockedFieldMapping($this->schema, array_map(static fn (FieldDefinition $field): string => $field->id->value.'@1', $this->schema->fields));
    }

    public function patch(mixed $v, ?DocumentRevision $expected = null): FieldPatch
    {
        return new FieldPatch([new SetField($this->field->id, FieldInput::from($v))], $this->schema->revision, $expected ?? DocumentRevision::absent());
    }
}
