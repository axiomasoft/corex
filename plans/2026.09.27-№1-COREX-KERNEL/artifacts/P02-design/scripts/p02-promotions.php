<?php

declare(strict_types=1);

require getcwd().'/vendor/autoload.php';

use CoreX\Tests\Golden\Support\GoldenSnapshot;
use CoreX\Tests\Golden\Support\Normalizer;
use CoreX\Tests\Golden\Support\RenameMap;

$plan = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$basenames = [];
foreach ($plan['moves'] as [$old, $new]) {
    if (str_contains($old, '/tests/') && str_contains($new, '/src/')) {
        $basenames[pathinfo($new, PATHINFO_FILENAME)] = true;
    }
}
$classes = [];
foreach ($plan['classes'] as $old => $new) {
    if (str_starts_with($new, 'CoreX\\Operations\\Testing\\') && isset($basenames[substr($new, strrpos($new, '\\') + 1)])) {
        $classes[] = $old;
    }
}

if (count($classes) !== 13) {
    throw new RuntimeException('Expected exactly the 13 map-declared test fixture promotions.');
}
$map = RenameMap::load('tests/Golden/rename-map.json');
function project(mixed $value, array $classes): mixed
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[is_string($key) ? project($key, $classes) : $key] = project($item, $classes);
        }

        return $out;
    }

    return is_string($value) ? preg_replace_callback('/\\\\?[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+/', static fn (array $m): string => ($classes[ltrim($m[0], '\\')] ?? $m[0]), $value) : $value;
}
$surface = Normalizer::normalize($map->apply(project(GoldenSnapshot::phpSurface($classes), $plan['classes'])));
file_put_contents('tests/Golden/promoted-fixtures.json', json_encode($surface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
echo 'promoted fixtures: '.count($surface)." pre-transfer signatures captured\n";
