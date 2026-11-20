<?php

declare(strict_types=1);

use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Internal\DatabaseFieldCatalogue;
use CoreX\CustomFields\Schemas\Internal\SchemaConflict;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use Illuminate\Support\Facades\DB;

uses(SchemaPgTestCase::class)->group('pg');

it('unifies declared and runtime fields with immutable code ownership and retired identity', function (): void {
    $schema = $this->catalogue->snapshot($this->scope, $this->entity);
    expect($schema->field('serial')->id->value)->toBe($this->declared->id->value)
        ->and(fn () => $this->schemas->plan($this->scope, FieldSchemaChange::retire($this->entity, $this->declared->id), $schema->revision))->toThrow(SchemaConflict::class);
    $extra = $this->field('location', 'text');
    expect($this->drain($this->admit(FieldSchemaChange::add($extra)))->state)->toBe('completed')
        ->and($this->drain($this->admit(FieldSchemaChange::retire($this->entity, $extra->id)))->state)->toBe('completed')
        ->and(fn () => $this->admit(FieldSchemaChange::add($extra)))->toThrow(SchemaConflict::class, 'retired-field-identity')
        ->and(DB::table('sys_attribute_definitions')->where('field_id', $extra->id->value)->count())->toBe(2);
    $tables = DB::select("SELECT relname FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname=current_schema() AND c.relkind='r' AND (relname LIKE 'sys_attribute_%' OR relname='sys_field_schema_heads')");
    expect(array_column($tables, 'relname'))->toHaveCount(7)->not->toContain('sys_attribute_eav_values', 'sys_attribute_eav_records');
});

it('holds the old machine-key reservation through switch and then allows a fresh UUID to use it', function (): void {
    $field = $this->field('old_key', 'int');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    $op = $this->admit(FieldSchemaChange::rename($this->entity, $field->id, 'new_key'));
    $this->step();
    $this->step();
    $this->step();
    expect($this->schemas->inspect($this->scope, $op)->state)->toBe('switched')
        ->and(DB::table('sys_attribute_key_reservations')->where('machine_key', 'old_key')->value('field_id'))->toBe($field->id->value)
        ->and(fn () => $this->catalogue->snapshot($this->scope, $this->entity)->field('old_key'))->toThrow(InvalidArgumentException::class);
    expect($this->drain($op)->state)->toBe('completed');
    $fresh = $this->field('old_key', 'text');
    expect($this->drain($this->admit(FieldSchemaChange::add($fresh)))->state)->toBe('completed')
        ->and($this->catalogue->snapshot($this->scope, $this->entity)->field('old_key')->id)->toEqual($fresh->id);
});

it('rejects conflicting names and code-owner impersonation without publishing another revision', function (): void {
    $field = $this->field('other', 'int');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    $base = $this->catalogue->snapshot($this->scope, $this->entity)->revision;
    expect(fn () => $this->schemas->plan($this->scope, FieldSchemaChange::rename($this->entity, $field->id, 'serial'), $base))->toThrow(SchemaConflict::class, 'machine-key-conflict')
        ->and(fn () => $this->schemas->plan($this->scope, FieldSchemaChange::add($this->field('forged', 'int', 'vendor/fixture')), $base))->toThrow(SchemaConflict::class, 'runtime-field-owner-conflict')
        ->and($this->catalogue->snapshot($this->scope, $this->entity)->revision)->toEqual($base);
});

it('requires a converter pinned to a real registered version for representation changes', function (): void {
    $old = $this->field('historical', 'text');
    expect($this->drain($this->admit(FieldSchemaChange::add($old)))->state)->toBe('completed');
    $new = $this->field('historical', 'int', id: $old->id);
    expect(fn () => $this->admit(FieldSchemaChange::revise($new)))->toThrow(SchemaConflict::class, 'versioned-converter-required')
        ->and(fn () => $this->admit(FieldSchemaChange::revise($new, 'fixture.missing', 2)))->toThrow(FieldFailure::class)
        ->and($this->catalogue->snapshot($this->scope, $this->entity)->field('historical')->type->value)->toBe('text');
});

it('declines catalogue access for unregistered entities and omits fields with denied uses', function (): void {
    expect(fn () => $this->catalogue->snapshot($this->scope, new EntityHandle('foreign.entity')))->toThrow(FieldFailure::class);
    $schema = $this->catalogue->snapshot($this->scope, $this->entity);
    $this->app->instance(FieldUsePolicy::class, new class implements FieldUsePolicy
    {
        public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
        {
            return FieldUsePlan::denied();
        }
    });
    $hidden = $this->app->make(DatabaseFieldCatalogue::class)->snapshot($this->scope, $this->entity);
    expect($hidden->fields)->toBe([])->and($hidden->revision)->toEqual($schema->revision)->and($hidden->mappingDigest)->toBe($schema->mappingDigest);
});
