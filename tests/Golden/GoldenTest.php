<?php

declare(strict_types=1);

use CoreX\Tests\Golden\GoldenTestCase;
use CoreX\Tests\Golden\Support\GoldenSnapshot;
use CoreX\Tests\Golden\Support\Normalizer;
use CoreX\Tests\Golden\Support\RenameMap;

uses(GoldenTestCase::class);

dataset('layers', ['php-surface', 'routes', 'events', 'wire']);

it('matches the frozen golden baseline modulo planned renames', function (string $layer): void {
    $root = dirname(__DIR__, 2);
    $classes = GoldenSnapshot::sourceClasses($root);
    $current = match ($layer) {
        'php-surface' => GoldenSnapshot::phpSurface($classes),
        'routes' => GoldenSnapshot::routes(),
        'events' => GoldenSnapshot::events($classes),
        'wire' => GoldenSnapshot::wire(sys_get_temp_dir().'/corex-golden-'.getmypid(), $root.'/tests/fixtures/public-error-contract.php'),
    };
    $path = __DIR__."/baseline/{$layer}.json";
    $map = RenameMap::load(__DIR__.'/rename-map.json');
    $actual = Normalizer::normalize($map->apply($current));

    if (getenv('GOLDEN_UPDATE') === '1') {
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
        expect(is_file($path))->toBeTrue();

        return; // Baseline generation is an explicit successful mode, never a skipped test.
    }

    $baseline = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    if ($layer === 'php-surface') {
        $baseline += json_decode((string) file_get_contents(__DIR__.'/promoted-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
    }
    $expected = Normalizer::normalize($map->apply($baseline));

    // Compare records separately so one API change produces a bounded, useful diff.
    expect(array_keys($actual))->toBe(array_keys($expected));
    foreach ($expected as $key => $record) {
        expect($actual[$key])->toBe($record);
    }
})->with('layers');
