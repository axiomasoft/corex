<?php

declare(strict_types=1);

use CoreX\Search\Support\SearchFilter;
use CoreX\Search\Support\SearchQuery;

it('bounds untrusted query shape before a provider is selected', function (): void {
    $query = new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump', filters: [new SearchFilter(field: 'status', values: ['active'])]);

    expect($query->limit)->toBe(25);
});

it('rejects a query beyond the public scan budget', function (): void {
    expect(fn (): SearchQuery => new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump', limit: 101))->toThrow(InvalidArgumentException::class);
});
