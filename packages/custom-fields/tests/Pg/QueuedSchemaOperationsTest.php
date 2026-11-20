<?php

declare(strict_types=1);

use CoreX\Context\Data\OperationKey;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemaDependencies;
use CoreX\CustomFields\Schemas\Contracts\SchemaFacts;
use CoreX\CustomFields\Schemas\Data\ClaimGeneration;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\PlanDigest;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Schemas\Internal\DatabaseFieldSchemas;
use CoreX\CustomFields\Schemas\Internal\SchemaClaimLost;
use CoreX\CustomFields\Schemas\Internal\SchemaConflict;
use CoreX\CustomFields\Schemas\Internal\SchemaJournal;
use CoreX\CustomFields\Tests\Pg\FailsSchemaFacts;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Support\Facades\DB;

uses(SchemaPgTestCase::class)->group('pg');

it('admits a single immutable plan and returns its exact replay across switch', function (): void {
    $base = $this->catalogue->snapshot($this->scope, $this->entity)->revision;
    $field = $this->field('weight', 'decimal');
    $change = FieldSchemaChange::add($field);
    $plan = $this->schemas->plan($this->scope, $change, $base);
    $other = $this->schemas->plan($this->scope, $change, $base);
    $key = new OperationKey('publish-weight', 1);
    $op = $this->schemas->apply($this->scope, $plan->ref, $plan->digest, $key);
    expect(fn () => $this->schemas->apply($this->scope, $other->ref, $other->digest, $key))->toThrow(SchemaConflict::class);
    $end = $this->drain($op);
    expect([$end->state, $end->blockers])->toBe(['completed', []])
        ->and($this->schemas->apply($this->scope, $plan->ref, $plan->digest, $key)->id)->toBe($op->id)
        ->and(DB::table('sys_outbox_messages')->count())->toBe(2);
    $rename = $this->admit(FieldSchemaChange::rename($this->entity, $field->id, 'mass'));
    expect($this->drain($rename)->state)->toBe('completed');
    $schema = $this->catalogue->snapshot($this->scope, $this->entity);
    expect($schema->field('mass')->id->value)->toBe($field->id->value)
        ->and(fn () => $schema->field('weight'))->toThrow(InvalidArgumentException::class)
        ->and(DB::table('sys_attribute_key_reservations')->where('machine_key', 'weight')->exists())->toBeFalse();
});

it('fences stale cancellation generations and refuses cancellation after switch', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('flag', 'bool')));
    $this->step();
    $status = $this->schemas->inspect($this->scope, $op);
    expect(fn () => $this->schemas->cancel($this->scope, $op, new ClaimGeneration(0)))->toThrow(SchemaClaimLost::class);
    $this->schemas->cancel($this->scope, $op, $status->generation);
    expect($this->drain($op)->state)->toBe('cancelled')
        ->and(DB::table('sys_attribute_key_reservations')->where('machine_key', 'flag')->exists())->toBeFalse();
    $second = $this->admit(FieldSchemaChange::add($this->field('flag', 'bool')));
    do {
        $this->step();
        $status = $this->schemas->inspect($this->scope, $second);
    } while ($status->state === 'verifying');
    expect($status->state)->toBe('switched')
        ->and(fn () => $this->schemas->cancel($this->scope, $second, $status->generation))->toThrow(SchemaConflict::class);
    expect($this->drain($second)->state)->toBe('completed');
});

it('keeps a planned index invisible and unbuilt during both plan and apply', function (): void {
    $snapshot = $this->catalogue->snapshot($this->scope, $this->entity);
    $field = $this->field('cycles', 'int', index: 'expression_btree');
    $plan = $this->schemas->plan($this->scope, FieldSchemaChange::add($field), $snapshot->revision);
    $stored = DB::table('sys_attribute_schema_operations')->where('id', $plan->ref->id)->sole();
    $name = json_decode($stored->plan, true, flags: JSON_THROW_ON_ERROR)['ddl']['name'];
    expect($stored->state)->toBe('planned')->and(DB::selectOne('SELECT to_regclass(?) AS index', ['public.'.$name])->index)->toBeNull();
    $this->schemas->apply($this->scope, $plan->ref, $plan->digest, new OperationKey('cycles', 1));
    expect($this->catalogue->snapshot($this->scope, $this->entity)->revision)->toEqual($snapshot->revision)
        ->and(DB::selectOne('SELECT to_regclass(?) AS index', ['public.'.$name])->index)->toBeNull()
        ->and(DB::table('sys_outbox_messages')->pluck('name')->all())->toBe(['fields.schema.requested']);
});

it('rejects an incorrect plan digest before creating a requested fact or pending head', function (): void {
    $snapshot = $this->catalogue->snapshot($this->scope, $this->entity);
    $plan = $this->schemas->plan($this->scope, FieldSchemaChange::add($this->field('speed', 'int')), $snapshot->revision);
    expect(fn () => $this->schemas->apply($this->scope, $plan->ref, new PlanDigest(str_repeat('0', 64)), new OperationKey('speed', 1)))->toThrow(SchemaConflict::class)
        ->and(DB::table('sys_outbox_messages')->count())->toBe(0)
        ->and(DB::table('sys_field_schema_heads')->sole()->pending_plan)->toBeNull();
});

it('rolls back head admission and operation key when its requested fact cannot append', function (): void {
    $this->app->instance(SchemaFacts::class, new class implements SchemaFacts
    {
        use FailsSchemaFacts;
    });
    $api = $this->app->make(DatabaseFieldSchemas::class);
    $base = $this->catalogue->snapshot($this->scope, $this->entity)->revision;
    $plan = $api->plan($this->scope, FieldSchemaChange::add($this->field('power', 'int')), $base);
    expect(fn () => $api->apply($this->scope, $plan->ref, $plan->digest, new OperationKey('power', 1)))->toThrow(RuntimeException::class, 'fixture-requested')
        ->and(DB::table('sys_attribute_schema_operations')->sole()->state)->toBe('planned')
        ->and(DB::table('sys_field_schema_heads')->sole()->pending_plan)->toBeNull();
});

it('serializes distinct admissions on the same base even with different operation keys', function (): void {
    $base = $this->catalogue->snapshot($this->scope, $this->entity)->revision;
    $one = $this->schemas->plan($this->scope, FieldSchemaChange::add($this->field('rpm', 'int')), $base);
    $two = $this->schemas->plan($this->scope, FieldSchemaChange::add($this->field('temp', 'int')), $base);
    $op = $this->schemas->apply($this->scope, $one->ref, $one->digest, new OperationKey('one-plan', 1));
    $apply = fn () => $this->schemas->apply($this->scope, $two->ref, $two->digest, new OperationKey('two-plans', 1));
    expect($apply)->toThrow(SchemaConflict::class)->and($this->drain($op)->state)->toBe('completed')->and($apply)->toThrow(SchemaConflict::class)
        ->and(DB::table('sys_outbox_messages')->where('name', 'fields.schema.requested')->count())->toBe(1);
});

it('rejects a restored stale F before returning a schema operation receipt', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('alarm', 'bool')));
    $scope = $this->scope;
    $stale = new DataScope($scope->deploymentId, $scope->accountId, $scope->workspaceId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, str_repeat('b', 64), $scope->principalKind, $scope->principalId);
    expect(fn () => $this->schemas->inspect($stale, $op))->toThrow(ScopeContextMismatch::class)
        ->and(DB::table('sys_attribute_schema_operations')->sole()->state)->toBe('queued');
});

it('refuses schema management when a principal has only an ordinary record grant', function (): void {
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static fn ($scope, $record): bool => $record->entity !== 'fields.schema'));
    $api = $this->app->make(DatabaseFieldSchemas::class);
    $base = $this->catalogue->snapshot($this->scope, $this->entity)->revision;
    expect(fn () => $api->plan($this->scope, FieldSchemaChange::add($this->field('count', 'int')), $base))->toThrow(FieldFailure::class, 'access-denied')
        ->and(DB::table('sys_attribute_schema_operations')->count())->toBe(0);
});

it('rolls back switch, definition revision and changed fact together on append failure', function (): void {
    $field = $this->field('runtime_field', 'int');
    $op = $this->admit(FieldSchemaChange::add($field));
    $this->step();
    $this->step();
    $before = DB::table('sys_field_schema_heads')->sole();
    $this->app->instance(SchemaFacts::class, new class implements SchemaFacts
    {
        use FailsSchemaFacts;
    });
    expect(fn () => $this->step())->toThrow(RuntimeException::class, 'fixture-changed')->and(DB::table('sys_field_schema_heads')->sole())->toEqual($before)
        ->and(DB::table('sys_attribute_definitions')->where('field_id', $field->id->value)->count())->toBe(0)
        ->and(DB::table('sys_outbox_messages')->where('name', 'fields.schema.changed')->count())->toBe(0)
        ->and($this->schemas->inspect($this->scope, $op)->state)->toBe('verifying');
});

it('rejects a tampered stored plan at durable restoration before executing its phase', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('qualified', 'bool')));
    // Simulate a damaged restored journal row at the exact logical and physical owner key.
    $journal = $this->app->make(SchemaJournal::class)->operation(DB::connection(), $this->scope, $op->id);
    $restored = json_decode($journal->value('plan'), true, flags: JSON_THROW_ON_ERROR);
    $restored['entity'] = 'other.entity';
    expect($journal->update(['plan' => json_encode($restored, JSON_THROW_ON_ERROR)]))->toBe(1);
    $this->step();
    expect($this->schemas->inspect($this->scope, $op)->blockers)->toBe(['stored-plan-integrity'])
        ->and(DB::table('sys_attribute_key_reservations')->where('machine_key', 'qualified')->exists())->toBeFalse();
});

it('keeps an index drifted since its original successful receipt out of a later schema switch', function (): void {
    $field = $this->field('kept', 'text', index: 'expression_btree');
    $created = $this->admit(FieldSchemaChange::add($field));
    expect($this->drain($created)->state)->toBe('completed');
    $rename = $this->admit(FieldSchemaChange::rename($this->entity, $field->id, 'kept_new'));
    $row = DB::table('sys_attribute_schema_operations')->where('id', $created->id)->sole();
    $index = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl']['name'];
    DB::statement('DROP INDEX public.'.$index);
    $end = $this->drain($rename);
    expect([$end->state, $end->blockers])->toBe(['blocked', ['index-identity-changed']])
        ->and($this->catalogue->snapshot($this->scope, $this->entity)->field('kept')->id)->toEqual($field->id);
});

it('returns the retained dependent references in the immutable plan and refuses destructive admission', function (): void {
    $field = $this->field('retained', 'int');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    $this->app->instance('test.field-reference', new class implements FieldSchemaDependencies
    {
        public function references(DataScope $scope, EntityHandle $entity, FieldId $field, SchemaRevision $revision): array
        {
            return ['reports:owned-saved-filter'];
        }
    });
    $this->app->tag(['test.field-reference'], FieldSchemaDependencies::class);
    $change = FieldSchemaChange::retire($this->entity, $field->id);
    $plan = $this->schemas->plan($this->scope, $change, $this->catalogue->snapshot($this->scope, $this->entity)->revision);
    expect($plan->dependencies)->toBe(['reports:owned-saved-filter'])->and($plan->blockers)->toBe($plan->dependencies)
        ->and($plan->change)->toBe($change)
        ->and(fn () => $this->schemas->apply($this->scope, $plan->ref, $plan->digest, new OperationKey('retire-retained', 1)))->toThrow(SchemaConflict::class)
        ->and(DB::table('sys_field_schema_heads')->sole()->pending_plan)->toBeNull();
});
