#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Blocking config() composition gate (P3.6 scanner, P7.11 blocking bit).
 *
 * Reports config('literal') calls outside allowlisted composition paths.
 * Exit 1 when blocking=true and any C1 remains.
 */
const EXIT_OK = 0;
const EXIT_CONFIG = 2;

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot.'/scripts/composition-boundary.json';

$config = loadBoundaryConfig($configPath);
$blocking = (bool) ($config['blocking'] ?? false);
$allowGlobs = $config['allow_globs'];
$allowPaths = $config['allow_paths'];
$findings = [];

foreach (glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR) ?: [] as $srcRoot) {
    foreach (walkPhpFiles($srcRoot) as $file) {
        $relativeFile = substr($file, strlen($repoRoot) + 1);

        if (isset($allowPaths[$relativeFile]) || pathMatchesAnyGlob($relativeFile, $allowGlobs)) {
            continue;
        }

        foreach (findConfigLiteralCalls($file) as $finding) {
            $findings[] = [
                'file' => $relativeFile,
                'line' => $finding['line'],
            ];
        }
    }
}

usort($findings, static fn (array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

foreach ($findings as $finding) {
    fwrite(STDOUT, sprintf("C1 %s:%d config()\n", $finding['file'], $finding['line']));
}

fwrite(STDOUT, sprintf(
    "config-composition-gate: blocking=%s\n",
    $blocking ? 'true' : 'false',
));
fwrite(STDOUT, sprintf("config-composition-gate: C1 count=%d\n", count($findings)));

if ($findings !== [] && $blocking) {
    exit(1);
}

fwrite(STDOUT, "config-composition-gate: OK\n");

exit(EXIT_OK);

/**
 * @return array{blocking: bool, allow_globs: list<string>, allow_paths: array<string, string>}
 */
function loadBoundaryConfig(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "config-composition-gate: missing {$path}\n");
        exit(EXIT_CONFIG);
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fwrite(STDERR, "config-composition-gate: invalid JSON in {$path}\n");
        exit(EXIT_CONFIG);
    }

    $allowGlobs = $decoded['allow_globs'] ?? null;

    if (! is_array($allowGlobs)) {
        fwrite(STDERR, "config-composition-gate: invalid allow_globs in {$path}\n");
        exit(EXIT_CONFIG);
    }

    foreach ($allowGlobs as $glob) {
        if (! is_string($glob) || $glob === '') {
            fwrite(STDERR, "config-composition-gate: invalid allow_globs entry in {$path}\n");
            exit(EXIT_CONFIG);
        }
    }

    $allowPaths = $decoded['allow_paths'] ?? [];

    if (! is_array($allowPaths)) {
        fwrite(STDERR, "config-composition-gate: invalid allow_paths in {$path}\n");
        exit(EXIT_CONFIG);
    }

    foreach ($allowPaths as $allowPath => $reason) {
        if (! is_string($allowPath) || $allowPath === '' || ! is_string($reason) || $reason === '') {
            fwrite(STDERR, "config-composition-gate: invalid allow_paths entry in {$path}\n");
            exit(EXIT_CONFIG);
        }
    }

    return [
        'blocking' => (bool) ($decoded['blocking'] ?? false),
        'allow_globs' => $allowGlobs,
        'allow_paths' => $allowPaths,
    ];
}

/**
 * @param  list<string>  $globs
 */
function pathMatchesAnyGlob(string $relativePath, array $globs): bool
{
    foreach (globCandidates($relativePath) as $candidate) {
        foreach ($globs as $glob) {
            if (pathMatchesGlob($candidate, str_replace('\\', '/', $glob))) {
                return true;
            }
        }
    }

    return false;
}

/**
 * §13 globs are package-relative (`*\/src/...`). §12 places the validate
 * command at `src/Support/Console`, which is the composition Console root.
 *
 * @return list<string>
 */
function globCandidates(string $path): array
{
    $normalized = str_replace('\\', '/', $path);
    $candidates = [$normalized];

    if (str_starts_with($normalized, 'packages/')) {
        $candidates[] = substr($normalized, strlen('packages/'));
    }

    $aliased = [];

    foreach ($candidates as $candidate) {
        $console = str_replace('/src/Support/Console/', '/src/Console/', $candidate);

        if ($console !== $candidate) {
            $aliased[] = $console;
        }
    }

    return array_values(array_unique([...$candidates, ...$aliased]));
}

function pathMatchesGlob(string $path, string $glob): bool
{
    return preg_match(globToRegex($glob), $path) === 1;
}

function globToRegex(string $glob): string
{
    $segments = explode('/', str_replace('\\', '/', $glob));
    $rendered = [];
    $last = count($segments) - 1;

    foreach ($segments as $index => $segment) {
        if ($segment === '**') {
            $rendered[] = '(?:[^/]+/)*';

            continue;
        }

        $piece = str_replace(['\*', '\?'], ['[^/]*', '[^/]'], preg_quote($segment, '#'));

        if ($index !== $last) {
            $piece .= '/';
        }

        $rendered[] = $piece;
    }

    return '#^'.implode('', $rendered).'$#';
}

/**
 * @return list<array{line: int}>
 */
function findConfigLiteralCalls(string $file): array
{
    $code = file_get_contents($file);

    if ($code === false) {
        return [];
    }

    $tokens = token_get_all($code, TOKEN_PARSE);
    $findings = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'config') {
            continue;
        }

        $j = $i + 1;

        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }

        if ($j >= $count || $tokens[$j] !== '(') {
            continue;
        }

        $j++;

        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }

        if ($j >= $count || ! is_array($tokens[$j])) {
            continue;
        }

        if (! in_array($tokens[$j][0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            continue;
        }

        if ($tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
            $line = $token[2];

            $findings[] = ['line' => $line];
        }
    }

    return $findings;
}

/**
 * @return list<string>
 */
function walkPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $current): bool => ! $current->isDir()
                || ! in_array($current->getFilename(), ['vendor', 'node_modules'], true),
        ),
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
