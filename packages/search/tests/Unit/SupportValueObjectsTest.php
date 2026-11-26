<?php

declare(strict_types=1);

use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Testing\SearchFixtures;

it('keeps projection and source values typed and distinguishes null from absent', function (): void {
    $values = new ProjectedValues(['paid' => null, 'quantity' => 0]);
    $ref = new RecordRef('equipment', 'pump-1');
    $record = SearchFixtures::record($ref, $values->values);
    expect($record->record)->toBe($ref)->and($record->values)->toEqual($values)
        ->and($record->revision)->toBe(SearchFixtures::record($ref, ['quantity' => 0, 'paid' => null])->revision)
        ->and($record->revision)->not->toBe(SearchFixtures::record($ref, ['quantity' => 0])->revision);
});

it('rejects unsafe duplicate or untyped projection, filter and candidate fields', function (): void {
    $field = new ProjectionField('name', FieldType::Text, [FieldUse::Search]);
    expect(fn () => new ProjectionFields([$field, $field], str_repeat('a', 64)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ProjectedValues(['name' => new stdClass]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ProjectedValues(['unsafe field' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SearchFilter('paid', [[true]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CandidatePage([new stdClass], null))->toThrow(InvalidArgumentException::class);
});

it('binds text witnesses to field identity and distinguishes exact scalar types, null and absence', function (): void {
    $digests = [];
    foreach ([[], ['name' => null], ['name' => '1'], ['name' => 1], ['name' => true], ['name' => '']] as $values) {
        $digests[] = new ProjectedValues($values)->fieldWitness('name');
    }
    expect(array_unique($digests))->toHaveCount(6)
        ->and(new ProjectedValues([])->fieldWitness('other'))->not->toBe($digests[0]);
});
