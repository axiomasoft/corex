#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Rename completeness gate (PLAN3 P02, D44/D45): no tracked file outside history may name a symbol,
 * namespace, composer package, path or identifier that P02 renamed away. Tokens come from
 * scripts/rename-completeness.json, generated before the transfer from the P00 maps.
 *
 * Usage: php scripts/rename-completeness-gate.php [--sections=classes,namespaces,packages,paths,identifiers]
 * Exit 0 OK, 1 violations (one line each), 2 configuration error.
 */

$root = dirname(__DIR__);
$manifestPath = $root.'/scripts/rename-completeness.json';
$manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;

if (! is_array($manifest) || ($manifest['schema'] ?? null) !== 'corex-rename-completeness/1') {
    fwrite(STDERR, "rename-completeness: missing or invalid scripts/rename-completeness.json\n");
    exit(2);
}

$sections = array_keys($manifest['tokens']);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--sections=')) {
        $sections = array_values(array_intersect($sections, explode(',', substr($arg, 11))));
    }
}

// Exact tokens: a FQCN/namespace token is compared whole, never by substring (CoreX\Data stays valid).
$fqcn = $prefix = $words = [];
foreach ($sections as $section) {
    foreach ($manifest['tokens'][$section] as $token) {
        match ($token['match']) {
            'fqcn' => $fqcn[$token['old']] = $section,
            'prefix' => $prefix[$token['old']] = $section,
            'word' => $words[$token['old']] = $section,
        };
    }
}
$wordPattern = null;
if ($words !== []) {
    $keys = array_keys($words);
    usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    $wordPattern = '~(?<![\w./@:-])('.implode('|', array_map(static fn (string $w): string => preg_quote($w, '~'), $keys)).')(?![\w-])~';
}

exec('git -C '.escapeshellarg($root).' ls-files -z', $out);
$files = array_filter(explode("\0", implode("\n", $out)));
$exclude = $manifest['exclude'] ?? [];
$violations = [];

foreach ($files as $file) {
    foreach ($exclude as $pattern) {
        if (preg_match($pattern, $file) === 1) {
            continue 2;
        }
    }
    $path = $root.'/'.$file;
    if (! is_file($path) || filesize($path) > 5_000_000) {
        continue;
    }
    $text = (string) file_get_contents($path);
    if (str_contains($text, "\0")) {
        continue; // binary
    }
    foreach (explode("\n", $text) as $i => $line) {
        preg_match_all('/(?<![\w\\\\])(?:\\\\\\\\|\\\\)?((?:CoreX|Flex)(?:(?:\\\\\\\\|\\\\)[A-Za-z_]\w*)+)/', $line, $m);
        foreach ($m[1] as $token) {
            $bare = str_replace('\\\\', '\\', $token);
            if (isset($fqcn[$bare])) {
                $violations[] = sprintf('%s:%d %s %s', $file, $i + 1, $fqcn[$bare], $bare);
                continue;
            }
            foreach ($prefix as $old => $section) {
                if ($bare === $old || str_starts_with($bare, $old.'\\')) {
                    $violations[] = sprintf('%s:%d %s %s', $file, $i + 1, $section, $bare);
                    break;
                }
            }
        }
        if ($wordPattern !== null && preg_match_all($wordPattern, $line, $w) > 0) {
            foreach ($w[1] as $token) {
                $violations[] = sprintf('%s:%d %s %s', $file, $i + 1, $words[$token], $token);
            }
        }
    }
}

foreach ($violations as $violation) {
    fwrite(STDERR, $violation."\n");
}
if ($violations !== []) {
    fwrite(STDERR, sprintf("rename-completeness FAIL: %d occurrence(s)\n", count($violations)));
    exit(1);
}
printf("rename-completeness OK: %d tokens, %d files, sections %s\n", count($fqcn) + count($prefix) + count($words), count($files), implode(',', $sections));
exit(0);
