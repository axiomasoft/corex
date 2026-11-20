<?php

declare(strict_types=1);

use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Tests\Support\ValueFixture;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldJson;
use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\CustomFields\Values\Data\RemoveField;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Tests\Support\CallerWorkScopeFixture;

it('requires an explicit version seven field ID and rejects generated-name identities', function (): void {
    expect(fn () => new FieldId('serial'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new FieldId('01900000-0000-5000-8000-000000000001'))->toThrow(InvalidArgumentException::class);
});

it('round trips definition constraints and versioned ownership without input aliases', function (): void {
    $definition = (new ValueFixture('decimal', ['scale' => 2, 'precision' => 34]))->field;
    expect(FieldDefinition::fromArray($definition->toArray())->toArray())->toBe($definition->toArray())
        ->and($definition->toArray())->toHaveKeys(['owner', 'uses', 'index_policy', 'codec_version', 'revision', 'localization_policy']);
});

it('rejects a duplicate use in a field declaration', function (): void {
    $field = (new ValueFixture)->field->toArray();
    $field['uses'] = ['read', 'read'];
    expect(fn () => FieldDefinition::fromArray($field))->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown lifecycle during definition hydration', function (): void {
    $field = (new ValueFixture)->field->toArray();
    $field['lifecycle'] = 'deleted';
    expect(fn () => FieldDefinition::fromArray($field))->toThrow(InvalidArgumentException::class);
});

it('rejects containment of a definition in another entity snapshot', function (): void {
    $fixture = new ValueFixture;
    expect(fn () => new FieldSchemaSnapshot(new EntityHandle('other.asset'), $fixture->schema->revision, $fixture->schema->mappingDigest, $fixture->schema->fields))
        ->toThrow(InvalidArgumentException::class, 'identity');
});

it('keeps rename identity stable and validates machine keys as logical codes', function (): void {
    $fixture = new ValueFixture;
    $change = FieldSchemaChange::rename($fixture->field->entity, $fixture->field->id, 'serial_number');
    expect(FieldSchemaChange::fromArray($change->toArray()))->toEqual($change)
        ->and($change->field)->toBe($fixture->field->id)
        ->and(fn () => FieldSchemaChange::rename($fixture->field->entity, $fixture->field->id, 'bad.key'))->toThrow(InvalidArgumentException::class);
});

it('refuses tampering with a field ID embedded in a schema plan', function (): void {
    $fixture = new ValueFixture;
    $row = FieldSchemaChange::add($fixture->field)->toArray();
    $row['field'] = $fixture->schema->field('secondary')->id->value;
    expect(fn () => FieldSchemaChange::fromArray($row))->toThrow(InvalidArgumentException::class, 'identity');
});

it('requires a converter ID and positive version together', function (): void {
    $field = (new ValueFixture)->field;
    expect(fn () => FieldSchemaChange::revise($field, 'acme.cast'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FieldSchemaChange::revise($field, null, 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FieldSchemaChange::revise($field, 'acme.cast', 0))->toThrow(InvalidArgumentException::class);
});

it('requires a valid content digest for a schema revision', function (): void {
    expect(fn () => new SchemaRevision('absent'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SchemaRevision(str_repeat('Q', 64)))->toThrow(InvalidArgumentException::class);
});

it('rejects nonrevision sentinels in value write preconditions', function (): void {
    expect(DocumentRevision::absent()->value)->toBe('absent')
        ->and(fn () => new DocumentRevision('any'))->toThrow(InvalidArgumentException::class);
});

it('rejects repeated record references and preserves a caller-specified batch order', function (): void {
    $a = new RecordRef('cas.equipment', 'second');
    $b = new RecordRef('cas.equipment', 'first');
    expect((new RecordSelection([$a, $b]))->records)->toEqual([$a, $b])
        ->and(fn () => new RecordSelection([$a, $a]))->toThrow(InvalidArgumentException::class);
});

it('bounds batch size before owner storage resolution', function (): void {
    $refs = array_map(fn ($i) => new RecordRef('cas.equipment', (string) $i), range(1, 51));
    expect(fn () => new RecordSelection($refs))->toThrow(InvalidArgumentException::class)
        ->and(new RecordSelection(array_slice($refs, 0, 50)))->toBeInstanceOf(RecordSelection::class);
});

it('rejects multiple operations against the same field within a patch', function (): void {
    $fixture = new ValueFixture;
    expect(fn () => new FieldPatch([new SetField($fixture->field->id, FieldInput::from('x')), new RemoveField($fixture->field->id)],
        $fixture->schema->revision, DocumentRevision::absent()))->toThrow(InvalidArgumentException::class);
});

it('binds receipt digests to expected revisions and the actor even on an identical operation key', function (): void {
    $fixture = new ValueFixture;
    $record = new RecordRef('cas.equipment', 'r');
    $scope = jsonbScope();
    $other = CallerWorkScopeFixture::forContext(jsonbContext(), 'someone-else', $scope->restoreEpoch);
    $patch = $fixture->patch('same');
    $original = $patch->token($scope, $record, 'same');
    expect($patch->token($other, $record, 'same')->payloadHash)->not->toBe($original->payloadHash)
        ->and($fixture->patch('same', new DocumentRevision(str_repeat('c', 64)))->token($scope, $record, 'same')->payloadHash)->not->toBe($original->payloadHash);
});

it('canonicalizes input map order and preserves list order for semantic replay', function (): void {
    $fixture = new ValueFixture;
    $scope = jsonbScope();
    $record = new RecordRef('cas.equipment', 'r');
    $digest = fn ($input) => $fixture->patch($input)->token($scope, $record, 'key')->payloadHash;
    expect($digest(['b' => 1, 'a' => 2]))->toBe($digest(['a' => 2, 'b' => 1]))
        ->and($digest(['a', 'b']))->not->toBe($digest(['b', 'a']));
});

it('distinguishes explicit null from removal in the canonical command digest', function (): void {
    $fixture = new ValueFixture;
    $scope = jsonbScope();
    $record = new RecordRef('cas.equipment', 'r');
    $remove = new FieldPatch([new RemoveField($fixture->field->id)], $fixture->schema->revision, DocumentRevision::absent());
    expect($fixture->patch(null)->token($scope, $record, 'same')->payloadHash)->not->toBe($remove->token($scope, $record, 'same')->payloadHash);
});

it('rejects duplicate JSON members instead of collapsing two commands to one digest', function (): void {
    expect(fn () => FieldJson::canonical('{"n":1,"n":2}'))->toThrow(InvalidArgumentException::class, 'Duplicate');
});

it('preserves arbitrarily large numeric lexemes when the caller submits raw JSON', function (): void {
    expect(FieldJson::canonical('{"n":1000000000000000000000000000000000001}'))->not->toBe(
        FieldJson::canonical('{"n":1000000000000000000000000000000000002}'));
});

it('rejects unknown operators at the enum boundary before compiling backend grammar', function (): void {
    expect(fn () => FieldOperator::from('raw-json-path'))->toThrow(ValueError::class)
        ->and(fn () => new FieldOperatorSet([FieldOperator::Eq, FieldOperator::Eq]))->toThrow(InvalidArgumentException::class);
});

it('distinguishes unary absence from binary equality with JSON null', function (): void {
    $id = (new ValueFixture)->field->id;
    expect(fn () => new FilterNode($id, FieldOperator::Eq))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new FilterNode($id, FieldOperator::IsAbsent, FieldInput::from(null)))->toThrow(InvalidArgumentException::class)
        ->and((new FilterNode($id, FieldOperator::Eq, FieldInput::from(null)))->operand->document->canonicalJson)->toBe('null');
});
