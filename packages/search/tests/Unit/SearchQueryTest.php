<?php

declare(strict_types=1);

use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Data\SearchSort;

it('bounds the public query and requires explicit text fields', function (): void {
    $entity = new EntityHandle('equipment');
    $query = new SearchQuery($entity, 'pump', ['name'], [new SearchFilter('paid', [true])], [new SearchSort('name')], ['name']);
    expect($query->limit)->toBe(25)
        ->and(fn () => new SearchQuery($entity, 'pump'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SearchQuery($entity, searchFields: ['name']))->toThrow(InvalidArgumentException::class);
});

it('rejects query limits outside 1 to 100', function (int $limit): void {
    expect(fn () => new SearchQuery(new EntityHandle('equipment'), limit: $limit))->toThrow(InvalidArgumentException::class);
})->with([0, 101]);
