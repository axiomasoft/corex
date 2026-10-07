#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\Naming;

use CoreX\Attributes\Tooling\SurfaceScanner;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Public API naming gate (P3.6 · P1-design §5.3).
 *
 * Blocks *new* generic short names in source-derived Api/Spi symbols.
 * Existing matches are frozen in naming-gate-grandfather.json.
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const COREX_SCHEMA_VERSION = 1;

/** @var list<string> */
const FORBIDDEN_EXACT = [
    'Dep',
    'Helper',
    'Util',
    'Common',
    'Misc',
    'Processor',
    'BaseService',
    'AbstractManager',
    'DataManager',
];

/** @var list<string> */
const FORBIDDEN_SUFFIXES = [
    'Info',
    'Manager',
    'Service',
    'Helper',
];

$repoRoot = dirname(__DIR__);
// The whole-monorepo AST/ownership graph exceeds PHP's 128 MiB CLI default.
ini_set('memory_limit', '1G');
require $repoRoot.'/vendor/autoload.php';

$grandfatherPath = $repoRoot.'/scripts/naming-gate-grandfather.json';

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--emit-grandfather') {
    emitGrandfatherSnapshot($repoRoot, $grandfatherPath);
    exit(EXIT_OK);
}

$grandfather = loadGrandfather($grandfatherPath);
$packages = discoverPackages($repoRoot);

$violations = [];

foreach ($packages as $dirName => $meta) {
    $srcDir = $repoRoot.'/packages/'.$dirName.'/src';

    if (! is_dir($srcDir)) {
        continue;
    }

    foreach (walkPhpFiles($srcDir) as $file) {
        $relativeFile = substr($file, strlen($repoRoot) + 1);
        $contents = (string) file_get_contents($file);

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch) !== 1) {
            continue;
        }

        $namespace = trim($namespaceMatch[1]).'\\';

        if ((($qualityMatchCount = preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $typeMatches)) === false || $qualityMatchCount === 0)) {
            continue;
        }

        foreach ($typeMatches[1] as $shortName) {
            if (! isForbiddenShortName($shortName)) {
                continue;
            }

            $fqcn = $namespace.$shortName;

            if (! namespaceCoveredByPublicApi($fqcn, $meta['public_api'])) {
                continue;
            }

            if (isset($grandfather[$fqcn])) {
                continue;
            }

            $line = lineOfTypeDeclaration($contents, $shortName);

            $violations[] = [
                'file' => $relativeFile,
                'line' => $line,
                'fqcn' => $fqcn,
            ];
        }
    }
}

if ($violations !== []) {
    usort($violations, static fn (array $a, array $b): int => [$a['file'], $a['line'], $a['fqcn']] <=> [$b['file'], $b['line'], $b['fqcn']]);

    foreach ($violations as $violation) {
        fwrite(STDERR, sprintf(
            "N1 %s:%d %s generic public-api name\n",
            $violation['file'],
            $violation['line'],
            $violation['fqcn'],
        ));
    }

    fwrite(STDERR, sprintf("naming-gate: %d violation(s)\n", count($violations)));
    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, "naming-gate: OK\n");
fwrite(STDOUT, sprintf("naming-gate: grandfather=%d\n", count($grandfather)));
exit(EXIT_OK);

/**
 * @return array<string, true>
 */
function loadGrandfather(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "naming-gate: missing {$path}\n");
        exit(EXIT_CONFIG);
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fwrite(STDERR, "naming-gate: invalid JSON in {$path}\n");
        exit(EXIT_CONFIG);
    }

    $set = [];

    foreach ($decoded as $fqcn) {
        if (! is_string($fqcn) || $fqcn === '') {
            fwrite(STDERR, "naming-gate: invalid grandfather entry in {$path}\n");
            exit(EXIT_CONFIG);
        }

        $set[$fqcn] = true;
    }

    return $set;
}

function emitGrandfatherSnapshot(string $repoRoot, string $path): void
{
    $packages = discoverPackages($repoRoot);
    $matches = [];

    foreach ($packages as $dirName => $meta) {
        $srcDir = $repoRoot.'/packages/'.$dirName.'/src';

        if (! is_dir($srcDir)) {
            continue;
        }

        foreach (walkPhpFiles($srcDir) as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch) !== 1) {
                continue;
            }

            $namespace = trim($namespaceMatch[1]).'\\';

            if ((($qualityMatchCount = preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $typeMatches)) === false || $qualityMatchCount === 0)) {
                continue;
            }

            foreach ($typeMatches[1] as $shortName) {
                if (! isForbiddenShortName($shortName)) {
                    continue;
                }

                $fqcn = $namespace.$shortName;

                if (namespaceCoveredByPublicApi($fqcn, $meta['public_api'])) {
                    $matches[] = $fqcn;
                }
            }
        }
    }

    $matches = array_values(array_unique($matches));
    sort($matches);

    file_put_contents($path, json_encode($matches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, sprintf("naming-gate: emitted %d grandfather entries to %s\n", count($matches), $path));
}

function isForbiddenShortName(string $shortName): bool
{
    if (in_array($shortName, FORBIDDEN_EXACT, true)) {
        return true;
    }

    return array_any(FORBIDDEN_SUFFIXES, fn ($suffix) => str_ends_with($shortName, $suffix) && strlen($shortName) > strlen($suffix));
}

/**
 * @param  list<string>  $publicApiPrefixes
 */
function namespaceCoveredByPublicApi(string $symbol, array $publicApiPrefixes): bool
{
    return in_array($symbol, $publicApiPrefixes, true);
}

function lineOfTypeDeclaration(string $contents, string $shortName): int
{
    if (preg_match('/\b(?:class|interface|trait|enum)\s+'.$shortName.'\b/', $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
        $offset = $match[0][1];

        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }

    return 1;
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

/**
 * @return array<string, array{name: string, dir: string, public_api: list<string>}>
 */
function discoverPackages(string $repoRoot): array
{
    $packages = [];
    $surface = (new SurfaceScanner)->scan($repoRoot);

    foreach ((($entries = glob($repoRoot.'/packages/*/composer.json')) === false ? [] : $entries) as $composerPath) {
        $dirName = basename(dirname($composerPath));
        $composer = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($composer) || ! isset($composer['name'])) {
            continue;
        }

        $extra = $composer['extra'] ?? null;
        $corex = is_array($extra) ? ($extra['corex'] ?? null) : null;

        if (! is_array($corex)) {
            fwrite(STDERR, "naming-gate: missing extra.corex in packages/{$dirName}/composer.json\n");
            exit(EXIT_CONFIG);
        }

        $schemaVersion = $corex['schema-version'] ?? null;

        if (! is_int($schemaVersion) || $schemaVersion !== COREX_SCHEMA_VERSION) {
            fwrite(STDERR, "naming-gate: invalid schema-version in packages/{$dirName}/composer.json\n");
            exit(EXIT_CONFIG);
        }

        $name = $composer['name'];

        if (! is_string($name)) {
            throw new RuntimeException('Invalid package owner');
        }
        $normalizedPublicApi = array_keys($surface->manifests[$name]['symbols'] ?? []);

        $packages[$dirName] = [
            'name' => $name,
            'dir' => $dirName,
            'public_api' => $normalizedPublicApi,
        ];
    }

    ksort($packages);

    return $packages;
}
