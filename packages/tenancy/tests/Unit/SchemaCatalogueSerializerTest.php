<?php

declare(strict_types=1);

use CoreX\Tenancy\Database\SchemaCatalogueSerializer;

it('encodes equivalent catalogue maps into identical canonical bytes', function (): void {
    $serializer = new SchemaCatalogueSerializer;
    $left = [
        'schemas' => [['name' => 'public', 'tables' => []]],
        'format_version' => 1,
        'extensions' => [],
    ];
    $right = [
        'extensions' => [],
        'format_version' => 1,
        'schemas' => [['tables' => [], 'name' => 'public']],
    ];

    expect($serializer->encode($left))->toBe($serializer->encode($right));
});
