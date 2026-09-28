#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Quality ratchet: PHPStan baseline entries and dependency-analyser `ratchet:` ignores
 * per package may only shrink. `--tighten` lowers ceilings to the measured values.
 */
$root = dirname(__DIR__);
$ceilingsPath = $root.'/scripts/quality-ceilings.json';
$ceilings = json_decode((string) file_get_contents($ceilingsPath), true, flags: JSON_THROW_ON_ERROR);

$measure = static function (string $file, string $pattern): int {
    if (! is_file($file)) {
        return 0;
    }
    preg_match_all($pattern, (string) file_get_contents($file), $m);

    return array_sum(array_map('intval', $m[1] ?? [])) ?: count($m[0] ?? []);
};

$actual = ['phpstan' => [], 'dependencies' => []];
$actual['phpstan']['root'] = $measure($root.'/phpstan-baseline.neon', '/^\s+count: (\d+)$/m');
foreach (glob($root.'/packages/*/composer.json') ?: [] as $manifest) {
    $dir = basename(dirname($manifest));
    $actual['phpstan'][$dir] = $measure(dirname($manifest).'/phpstan-baseline.neon', '/^\s+count: (\d+)$/m');
    $actual['dependencies'][$dir] = substr_count((string) @file_get_contents(dirname($manifest).'/composer-dependency-analyser.php'), '// ratchet:');
}

if (in_array('--tighten', $argv, true)) {
    foreach ($actual as $kind => $values) {
        foreach ($values as $key => $value) {
            $ceilings[$kind][$key] = min($value, $ceilings[$kind][$key] ?? $value);
        }
        ksort($ceilings[$kind]);
    }
    file_put_contents($ceilingsPath, json_encode($ceilings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

$violations = [];
foreach ($actual as $kind => $values) {
    foreach ($values as $key => $value) {
        $ceiling = $ceilings[$kind][$key] ?? 0;

        if ($value > $ceiling) {
            $violations[] = "{$kind}.{$key}: {$value} > ceiling {$ceiling}";
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "ceilings FAIL:\n  ".implode("\n  ", $violations)."\n");
    exit(1);
}
echo "ceilings OK\n";
