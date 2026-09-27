<?php

declare(strict_types=1);

use CoreX\Attributes\Internal\Schema\StaticFieldCatalogue;

it('returns an empty snapshot for an entity without static definitions', function (): void {
    $catalogue = new StaticFieldCatalogue(definitions: [], drivers: ['jsonb' => true]);

    $snapshot = $catalogue->snapshot(jsonbScope(), 'contact');

    expect($snapshot->entity)->toBe('contact')
        ->and($snapshot->activeFields)->toBe([])
        ->and($snapshot->schemaHash)->toHaveLength(64)
        ->and($snapshot->driverCapabilities)->toBe(['jsonb' => true]);
});

it('projects configured static definitions', function (): void {
    $catalogue = new StaticFieldCatalogue(
        definitions: [
            'contact' => [
                ['code' => 'website', 'type' => 'text', 'required' => false, 'searchable' => true, 'meta' => []],
            ],
        ],
        drivers: ['jsonb' => true],
    );

    $snapshot = $catalogue->snapshot(jsonbScope(), 'contact');

    expect($snapshot->activeFields)->toBe([
        ['code' => 'website', 'type' => 'text', 'required' => false, 'searchable' => true, 'meta' => []],
    ]);
});
