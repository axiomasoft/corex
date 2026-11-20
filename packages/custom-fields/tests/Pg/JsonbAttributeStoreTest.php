<?php

declare(strict_types=1);

use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Query\Internal\PostgresFieldMatches;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\ValueFixture;
use CoreX\CustomFields\Types\Contracts\FieldCodec;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Data\FieldTypeDescriptor;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Types\Internal\RegisteredFieldTypes;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\CustomFields\Values\Data\RemoveField;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('distinguishes stored null and removal in snapshots and executed owner predicates', function (): void {
    $fixtures = new ValueFixture;
    $fixtures->bind($this->app);
    $scope = jsonbScope();
    $values = $this->app->make(FieldValues::class);
    $record = new RecordRef('cas.equipment', 'stamped');
    $selection = new FieldSelection([$fixtures->field->id->value]);
    $patch = $fixtures->patch(null);
    $null = $values->update($scope, $record, $patch, $patch->token($scope, $record, 'set-null'));
    expect($values->read($scope, $record, $selection)->values[$fixtures->field->id->value]->document->canonicalJson)->toBe('null');
    $queries = $this->app->make(PostgresFieldMatches::class);
    $missing = new RecordRef('cas.equipment', 'never-written');
    $refs = new RecordSelection([$record, $missing]);
    $match = fn (FieldOperator $op): array => $queries->matching($scope, $fixtures->field->entity, new FilterNode($fixtures->field->id, $op), $refs);
    expect($match(FieldOperator::IsNull))->toEqual([$record])->and($match(FieldOperator::IsAbsent))->toEqual([$missing]);
    $remove = new FieldPatch([new RemoveField($fixtures->field->id)], $fixtures->schema->revision, $null->valueRevision);
    $values->update($scope, $record, $remove, $remove->token($scope, $record, 'remove'));
    expect($values->read($scope, $record, $selection)->values)->toBe([])
        ->and($match(FieldOperator::IsNull))->toBe([])->and($match(FieldOperator::IsAbsent))->toHaveCount(2);
});

it('applies exact PostgreSQL decimal predicates and rejects new stale and reused conflicting tokens', function (): void {
    $fixture = new ValueFixture('decimal', ['scale' => 2]);
    $fixture->bind($this->app);
    $values = $this->app->make(FieldValues::class);
    $scope = jsonbScope();
    $record = new RecordRef('cas.equipment', 'first');
    $patch = $fixture->patch('99999999999999999999999.01');
    $receipt = $values->update($scope, $record, $patch, $patch->token($scope, $record, 'same'));
    $other = $fixture->patch('99999999999999999999999.02');
    expect(fn () => $values->update($scope, $record, $other, $other->token($scope, $record, 'same')))->toThrow(FieldFailure::class, 'idempotency-conflict')
        ->and(fn () => $values->update($scope, $record, $other, $other->token($scope, $record, 'different')))->toThrow(FieldFailure::class, 'document-revision-mismatch');
    $node = new FilterNode($fixture->field->id, FieldOperator::Lt, FieldInput::from('99999999999999999999999.02'));
    $matches = $this->app->make(PostgresFieldMatches::class)->matching($scope, $fixture->field->entity, $node, new RecordSelection([$record]));
    expect($matches)->toEqual([$record])->and(DB::table('sys_attribute_documents')->sole()->document_revision)->toBe($receipt->valueRevision->value);
});

it('evaluates multiselect any/all with nullable and absent documents in the same corpus', function (): void {
    $fixture = new ValueFixture('multiselect');
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $values = $this->app->make(FieldValues::class);
    foreach (['set' => ['a', 'b'], 'empty' => [], 'null' => null] as $id => $input) {
        $ref = new RecordRef('cas.equipment', $id);
        $patch = $fixture->patch($input);
        $values->update($scope, $ref, $patch, $patch->token($scope, $ref, 'create'));
    }
    $refs = new RecordSelection(array_map(static fn (string $id): RecordRef => new RecordRef('cas.equipment', $id), ['set', 'empty', 'null', 'absent']));
    foreach ([FieldOperator::Any, FieldOperator::All] as $operator) {
        $filter = new FilterNode($fixture->field->id, $operator, FieldInput::from(['b']));
        expect($this->app->make(PostgresFieldMatches::class)->matching($scope, $fixture->field->entity, $filter, $refs))->toEqual([$refs->records[0]]);
    }
});

it('returns the committed receipt after document removal without materializing it on replay', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $ref = new RecordRef('cas.equipment', 'removed');
    $scope = jsonbScope();
    $values = $this->app->make(FieldValues::class);
    $patch = $fixture->patch('one');
    $token = $patch->token($scope, $ref, 'original');
    $original = $values->update($scope, $ref, $patch, $token);
    DB::table('sys_attribute_documents')->where('record_id', $ref->id)->delete();
    expect($values->update($scope, $ref, $patch, $token))->toEqual($original)
        ->and(DB::table('sys_attribute_documents')->count())->toBe(0)
        ->and(DB::table('sys_outbox_messages')->count())->toBe(1);
});

it('loads fifty selected records in input order with a single owner document select', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $records = array_map(static fn ($i) => new RecordRef('cas.equipment', 'r-'.$i), range(49, 0));
    $values = $this->app->make(FieldValues::class);
    foreach ([$records[0], $records[49]] as $ref) {
        $patch = $fixture->patch($ref->id);
        $values->update($scope, $ref, $patch, $patch->token($scope, $ref, 'first'));
    }
    DB::connection()->enableQueryLog();
    $batch = $values->readMany($scope, new RecordSelection($records), new FieldSelection([$fixture->field->id->value]));
    $selects = array_filter(DB::connection()->getQueryLog(), static fn ($log) => str_contains($log['query'], 'sys_attribute_documents'));
    expect($selects)->toHaveCount(1)->and($batch->items)->toHaveCount(50)
        ->and(array_map(fn ($item) => $item->record, $batch->items))->toEqual($records)
        ->and($batch->items[48]->valueRevision->value)->toBe('absent');
});

it('rejects stale schema even when the new document is absent and rolls back its materialization', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $ref = new RecordRef('cas.equipment', 'stale-schema');
    $patch = new FieldPatch($fixture->patch('new')->operations, new SchemaRevision(str_repeat('d', 64)), DocumentRevision::absent());
    expect(fn () => $this->app->make(FieldValues::class)->update($scope, $ref, $patch, $patch->token($scope, $ref, 'create')))->toThrow(FieldFailure::class, 'schema-revision-mismatch')
        ->and(DB::table('sys_attribute_documents')->count())->toBe(0)->and(DB::table('sys_outbox_messages')->count())->toBe(0);
});

it('rejects a forged client hash before changing the existing document or receipt ledger', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $ref = new RecordRef('cas.equipment', 'tampered');
    $patch = $fixture->patch('value');
    $token = new WriteToken('token', str_repeat('f', 64), 'absent');
    expect(fn () => $this->app->make(FieldValues::class)->update($scope, $ref, $patch, $token))->toThrow(FieldFailure::class, 'idempotency-conflict')
        ->and(DB::table('sys_attribute_documents')->count())->toBe(0)->and(DB::table('sys_attribute_write_tokens')->count())->toBe(0);
});

it('returns a structured failure for a bad typed operand without changing an earlier field in the same patch', function (): void {
    $fixture = new ValueFixture('decimal', ['scale' => 2]);
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $ref = new RecordRef('cas.equipment', 'invalid');
    $secondary = $fixture->schema->field('secondary')->id;
    $patch = new FieldPatch([new SetField($secondary, FieldInput::from('first')), new SetField($fixture->field->id, FieldInput::from('11.234'))],
        $fixture->schema->revision, DocumentRevision::absent());

    try {
        $this->app->make(FieldValues::class)->update($scope, $ref, $patch, $patch->token($scope, $ref, 'atomic'));
        $this->fail('Expected a refused patch.');
    } catch (FieldFailure $error) {
        expect($error->errorDescriptor()->path)->toBe('/operations/1/input')->and($error->errorDescriptor()->code)->toBe('custom-fields.invalid_field_value');
    }
    expect(DB::table('sys_attribute_documents')->count())->toBe(0);
});

it('rejects noncollection any and all operands at compile time before the document predicate runs', function (): void {
    $fixture = new ValueFixture('multiselect');
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $record = new RecordRef('cas.equipment', 'colors');
    $patch = $fixture->patch(['red', 'blue']);
    $this->app->make(FieldValues::class)->update($scope, $record, $patch, $patch->token($scope, $record, 'colors'));
    $records = new RecordSelection([$record]);
    $filters = $this->app->make(FieldFilter::class);
    $matcher = $this->app->make(PostgresFieldMatches::class);
    DB::connection()->enableQueryLog();
    foreach ([FieldOperator::Any, FieldOperator::All] as $operator) {
        foreach ([null, 'red', ['key' => 'red']] as $invalid) {
            $filter = new FilterNode($fixture->field->id, $operator, FieldInput::from($invalid));
            expect(fn () => $filters->compile($scope, $fixture->schema, $filter))->toThrow(InvalidArgumentException::class)
                ->and(fn () => $matcher->matching($scope, $fixture->field->entity, $filter, $records))->toThrow(InvalidArgumentException::class);
        }
    }
    expect(array_filter(DB::connection()->getQueryLog(), static fn ($entry) => str_contains($entry['query'], 'sys_attribute_documents')))->toBe([]);
    $empty = FieldInput::from([]);
    expect($matcher->matching($scope, $fixture->field->entity, new FilterNode($fixture->field->id, FieldOperator::Any, $empty), $records))->toBe([])
        ->and($matcher->matching($scope, $fixture->field->entity, new FilterNode($fixture->field->id, FieldOperator::All, $empty), $records))->toEqual([$record]);
});

it('rejects a collection operator whose external codec encodes a valid list as a scalar', function (): void {
    $fixture = new ValueFixture('fixture.choices');
    $fixture->bind($this->app);
    $codec = Mockery::mock(FieldCodec::class);
    $codec->shouldReceive('encode')->twice()->andReturn(new JsonDocument('"opaque"'));
    $type = Mockery::mock(FieldType::class);
    $descriptor = new FieldTypeDescriptor('fixture.scalar', 1, $codec, $fixture->field->uses, multiple: true);
    $type->shouldReceive('id')->andReturn($fixture->field->type);
    $type->shouldReceive('definition')->andReturn($descriptor);
    $type->shouldReceive('normalize')->twice()->andReturn(FieldValue::from(['red']));
    $type->shouldReceive('operators')->andReturn(new FieldOperatorSet([FieldOperator::Any, FieldOperator::All]));
    $this->app->instance(FieldTypes::class, new RegisteredFieldTypes([$type], []));
    $compile = $this->app->make(FieldFilter::class);
    foreach ([FieldOperator::Any, FieldOperator::All] as $operator) {
        $filter = new FilterNode($fixture->field->id, $operator, FieldInput::from(['red']));
        expect(fn () => $compile->compile(jsonbScope(), $fixture->schema, $filter))->toThrow(InvalidArgumentException::class, 'array operand');
    }
});
