<?php

declare(strict_types=1);

use CoreX\Tests\Golden\GoldenTestCase;
use CoreX\Tests\Golden\Support\GoldenSnapshot;
use CoreX\Tests\Golden\Support\Normalizer;
use CoreX\Tests\Golden\Support\RenameMap;
use PHPUnit\Framework\Attributes\DataProvider;

final class GoldenTest extends GoldenTestCase
{
    /** @return list<array{'php-surface'|'routes'|'events'|'wire'}> */
    public static function layers(): array
    {
        return [['php-surface'], ['routes'], ['events'], ['wire']];
    }

    /** @param 'php-surface'|'routes'|'events'|'wire' $layer */
    #[DataProvider('layers')]
    public function test_matches_the_frozen_golden_baseline_modulo_planned_renames(string $layer): void
    {
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
            self::assertTrue(is_file($path));

            return; // Baseline generation is an explicit successful mode, never a skipped test.
        }

        $baseline = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        assert(is_array($baseline));

        if ($layer === 'php-surface') {
            $promoted = json_decode((string) file_get_contents(__DIR__.'/promoted-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($promoted));
            $baseline += $promoted;
        }
        $expected = Normalizer::normalize($map->apply($baseline));

        // Compare records separately so one API change produces a bounded, useful diff.
        assert(is_array($actual) && is_array($expected));
        self::assertSame(array_keys($expected), array_keys($actual));
        foreach ($expected as $key => $record) {
            self::assertSame($record, $actual[$key]);
        }
    }
}
