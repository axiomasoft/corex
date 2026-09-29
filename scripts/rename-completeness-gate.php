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

$tokens = $manifest['tokens'] ?? null;

if (! is_array($tokens)) {
    fwrite(STDERR, "rename-completeness: invalid tokens\n");
    exit(2);
}
foreach ($tokens as $section => $rows) {
    if (! is_string($section) || ! is_array($rows)) {
        fwrite(STDERR, "rename-completeness: invalid token section\n");
        exit(2);
    }
    foreach ($rows as $token) {
        if (! is_array($token) || ! is_string($token['old'] ?? null)
            || ! in_array($token['match'] ?? null, ['fqcn', 'prefix', 'word'], true)) {
            fwrite(STDERR, "rename-completeness: invalid token\n");
            exit(2);
        }
    }
}
/** @var array<string, list<array{old: string, match: 'fqcn'|'prefix'|'word', kind?: string}>> $tokens */
$sections = array_keys($tokens);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--sections=')) {
        $sections = array_values(array_intersect($sections, explode(',', substr($arg, 11))));
    }
}

// Exact tokens: a FQCN/namespace token is compared whole, never by substring (CoreX\Data stays valid).
$fqcn = $prefix = $words = $configFiles = [];
foreach ($sections as $section) {
    foreach ($tokens[$section] as $token) {
        match ($token['match']) {
            'fqcn' => $fqcn[$token['old']] = $section,
            'prefix' => $prefix[$token['old']] = $section,
            'word' => $words[$token['old']] = $section,
        };

        if ($section === 'identifiers' && ($token['kind'] ?? null) === 'config') {
            $configFiles[$token['old'].'.php'] = $section;
        }
    }
}
$wordPattern = null;

if ($words !== []) {
    $keys = array_keys($words);
    usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    $wordPattern = '(?<![\w./@:-])(?P<word>'.implode('|', array_map(static fn (string $w): string => preg_quote($w, '~'), $keys)).')(?![\w-])';

    if ($configFiles !== []) {
        $fileKeys = array_keys($configFiles);
        usort($fileKeys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $wordPattern = '(?<![\w.-])(?P<config_file>'.implode('|', array_map(static fn (string $w): string => preg_quote($w, '~'), $fileKeys)).')(?![\w.-])|'.$wordPattern;
    }
    $wordPattern = '~'.$wordPattern.'~';
}

exec('git -C '.escapeshellarg($root).' ls-files -z', $out);
$files = array_filter(explode("\0", implode("\n", $out)), static fn (string $file): bool => $file !== '');
$exclude = $manifest['exclude'] ?? [];

if (! is_array($exclude)) {
    fwrite(STDERR, "rename-completeness: invalid exclusions\n");
    exit(2);
}
foreach ($exclude as $pattern) {
    if (! is_string($pattern)) {
        fwrite(STDERR, "rename-completeness: invalid exclusion pattern\n");
        exit(2);
    }
}
/** @var list<string> $exclude */
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

            // PLAN3.D63 reuses only these namespace declarations for API metadata.
            // Every renamed custom-fields class and child namespace remains forbidden.
            if (($fqcn[$bare] ?? null) === 'namespaces'
                && in_array($bare, ['CoreX\\Attributes', 'CoreX\\Attributes\\Internal'], true)) {
                continue;
            }

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

        if ($wordPattern !== null && preg_match_all($wordPattern, $line, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $hit) {
                $token = ($hit['config_file'] ?? '') !== '' ? $hit['config_file'] : $hit['word'];
                $violations[] = sprintf('%s:%d %s %s', $file, $i + 1, $configFiles[$token] ?? $words[$token], $token);
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
printf("rename-completeness OK: %d tokens, %d files, sections %s\n", count($fqcn) + count($prefix) + count($words) + count($configFiles), count($files), implode(',', $sections));
exit(0);
