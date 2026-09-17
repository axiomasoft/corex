<?php

declare(strict_types=1);

use CoreX\Views\Data\ViewField;
use CoreX\Views\Data\ViewSchema;
use CoreX\Views\Internal\ViewConfigValidator;

it('rejects an unsafe saved-view filter before a consumer can map it to SQL', function (): void {
    $validator = new ViewConfigValidator;
    $schema = new ViewSchema('neutral/1', 'lab.sample', [
        new ViewField('name', 'string', ['eq', 'ne', 'in', 'is_null'], true, true, true, true, 'name'),
    ]);

    expect(fn (): array => $validator->validate([
        'version' => 'saved-view/1',
        'filters' => ['field' => 'name', 'op' => 'raw_sql', 'value' => 'select 1'],
        'sorts' => [],
        'columns' => ['name'],
        'groupBy' => null,
        'presentation' => [],
    ], $schema))->toThrow(InvalidArgumentException::class);
});
