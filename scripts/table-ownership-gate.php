#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Table ownership gate (P3.7 · D19): T1/T2/T4 against docs/architecture/table-ownership.md.
 *
 * T4 cross-package FK: migration package must not reference another owner's table via
 * foreign()/constrained(), except D102 (aut_role_assignments → users/wsp_workspaces via
 * raw SQL is out of scope) and except established platform references to users /
 * wsp_workspaces from packages other than axiomasoft/corex (frozen CRM/tasks parity).
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const CORE_PLATFORM_OWNER = 'axiomasoft/corex';

/** @var list<string> */
const PLATFORM_REFERENCE_TABLES = ['users', 'wsp_workspaces'];

$repoRoot = dirname(__DIR__);
$catalogPath = $repoRoot.'/docs/architecture/table-ownership.md';

if (! is_file($catalogPath)) {
    fwrite(STDERR, "table-ownership-gate: missing catalog {$catalogPath}\n");

    exit(EXIT_CONFIG);
}

$catalog = parseCatalog($catalogPath);
$packageDirs = loadPackageComposerNames($repoRoot);
$configDefaults = loadConfigTableDefaults($repoRoot);

$creates = extractSchemaCreates($repoRoot, $packageDirs, $configDefaults);
$violations = [];
require_once __DIR__.'/schema-gate.php';
/** @var array{packages: array<string, string>, sets: list<array{id: string, package: string, name: string, scope: string, path: string, version: int, drivers: list<string>, lifecycle: string, requires: list<string>, connection: ?string}>} $manifest */
$manifest = json_decode((string) file_get_contents(__DIR__.'/schema-sets.json'), true, flags: JSON_THROW_ON_ERROR);
$coveredPaths = array_column($manifest['sets'], 'path');
foreach ((new SchemaGate($repoRoot, $manifest, []))->run() as $hit) {
    if ($hit['code'] === 'S9') {
        $violations[] = ['S9', $hit['file'].':'.$hit['line'], $hit['message']];
    }
}

foreach ($creates as $create) {
    $table = $create['table'];
    $owner = $create['owning_package'];
    $file = $create['migration'];

    if (in_array(dirname($file), $coveredPaths, true)) {
        continue; // S9 owns the registered paths, including package and scope checks.
    }

    if (! isset($catalog[$table])) {
        $violations[] = ['T1', $file, "table '{$table}' missing from catalog"];

        continue;
    }

    if ($catalog[$table] !== $owner) {
        $violations[] = ['T2', $file, "table '{$table}' owned by {$catalog[$table]} in catalog, migration package is {$owner}"];
    }
}

foreach (collectCrossPackageForeignKeys($repoRoot, $packageDirs, $catalog) as $hit) {
    $violations[] = ['T4', $hit['file'], $hit['message']];
}

if ($violations !== []) {
    usort($violations, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

    foreach ($violations as [$code, $file, $message]) {
        fwrite(STDERR, "{$code} {$file}: {$message}\n");
    }

    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, 'table-ownership-gate: OK ('.count($creates).' tables, catalog '.count($catalog)." rows)\n");

exit(EXIT_OK);

/**
 * @return array<string, string> table => owning_package
 */
function parseCatalog(string $path): array
{
    $catalog = [];

    foreach ((($lines = file($path)) === false ? throw new RuntimeException('Unreadable table catalog') : $lines) as $line) {
        if (preg_match('/^\| ([^|]+) \| ([a-z][a-z0-9-]*\/[a-z][a-z0-9-]*) \|/', $line, $match) !== 1) {
            continue;
        }

        $table = trim($match[1]);

        if ($table === 'table') {
            continue;
        }

        $catalog[$table] = trim($match[2]);
    }

    return $catalog;
}

/**
 * @return array<string, string> absolute package dir => composer name
 */
function loadPackageComposerNames(string $repoRoot): array
{
    $map = [];

    foreach ((($entries = glob($repoRoot.'/packages/*/composer.json')) === false ? [] : $entries) as $composerFile) {
        $data = json_decode((string) file_get_contents($composerFile), true);

        if (! is_array($data) || ! is_string($data['name'] ?? null)) {
            continue;
        }

        $map[dirname($composerFile)] = $data['name'];
    }

    return $map;
}

/**
 * @return array<string, string> Config::method => default table name
 */
function loadConfigTableDefaults(string $repoRoot): array
{
    $defaults = [];
    $configPath = $repoRoot.'/packages/core/src/Config/Config.php';

    if (! is_file($configPath)) {
        return $defaults;
    }

    $src = (string) file_get_contents($configPath);

    if ((($qualityMatchCount = preg_match_all(
        '/public\s+static\s+function\s+(\w+Table)\(\)[^{]*\{[^}]*config\([^,]+,\s*\'([^\']+)\'\)[^}]*\}/',
        $src,
        $matches,
        PREG_SET_ORDER,
    )) !== false && $qualityMatchCount > 0)) {
        foreach ($matches as $match) {
            $defaults[$match[1]] = $match[2];
        }
    }

    return $defaults;
}

/**
 * @param  array<string, string>  $packageDirs
 * @param  array<string, string>  $configDefaults
 * @return list<array{table: string, owning_package: string, migration: string}>
 */
function extractSchemaCreates(string $repoRoot, array $packageDirs, array $configDefaults): array
{
    $creates = [];

    foreach ((($entries = glob($repoRoot.'/packages/*/database/migrations', GLOB_ONLYDIR)) === false ? [] : $entries) as $migDir) {
        $pkgDir = dirname(dirname($migDir));
        $ownerPkg = $packageDirs[$pkgDir] ?? basename($pkgDir);

        $files = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migDir));

        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);

            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php')) {
                continue;
            }

            if (str_contains($file->getPathname(), '/tests/')) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        foreach ($files as $filePath) {
            $relPath = ltrim(str_replace($repoRoot.'/', '', $filePath), '/');
            $src = (string) file_get_contents($filePath);
            $consts = [];

            if ((($qualityMatchCount = preg_match_all(
                '/(?:private|protected|public)?\s*const\s+(?:string\s+)?(\w+)\s*=\s*\'([^\']+)\'/',
                $src,
                $constMatches,
            )) !== false && $qualityMatchCount > 0)) {
                foreach ($constMatches[1] as $i => $name) {
                    $consts[$name] = $constMatches[2][$i];
                }
            }

            $varMap = [];

            if ((($qualityMatchCount = preg_match_all('/\$(\w+)\s*=\s*Config::(\w+)\(\)/', $src, $varMatches)) !== false && $qualityMatchCount > 0)) {
                foreach ($varMatches[1] as $i => $varName) {
                    $method = $varMatches[2][$i];

                    if (isset($configDefaults[$method])) {
                        $varMap[$varName] = $configDefaults[$method];
                    }
                }
            }

            if ((($qualityMatchCount = preg_match_all(
                '/Schema::create\(\s*(?:\'([^\']+)\'|"([^"]+)"|self::(\w+)|\$(\w+)|Config::(\w+)\(\))\s*,/',
                $src,
                $matches,
                PREG_SET_ORDER,
            )) === false || $qualityMatchCount === 0)) {
                continue;
            }

            foreach ($matches as $match) {
                $tableName = resolveTableName($match, $consts, $varMap, $configDefaults);

                if ($tableName === null) {
                    continue;
                }

                $creates[] = [
                    'table' => $tableName,
                    'owning_package' => $ownerPkg,
                    'migration' => $relPath,
                ];
            }
        }
    }

    return $creates;
}

/**
 * @param  array<int, string>  $match
 * @param  array<string, string>  $consts
 * @param  array<string, string>  $varMap
 * @param  array<string, string>  $configDefaults
 */
function resolveTableName(array $match, array $consts, array $varMap, array $configDefaults): ?string
{
    if ($match[1] !== '') {
        return $match[1];
    }

    if ($match[2] !== '') {
        return $match[2];
    }

    if ($match[3] !== '' && isset($consts[$match[3]])) {
        return $consts[$match[3]];
    }

    if ($match[4] !== '' && isset($varMap[$match[4]])) {
        return $varMap[$match[4]];
    }

    if (($match[5] ?? '') !== '' && isset($configDefaults[$match[5]])) {
        return $configDefaults[$match[5]];
    }

    return null;
}

/**
 * @param  array<string, string>  $packageDirs
 * @param  array<string, string>  $catalog
 * @return list<array{file: string, message: string}>
 */
function collectCrossPackageForeignKeys(string $repoRoot, array $packageDirs, array $catalog): array
{
    $hits = [];

    foreach ((($entries = glob($repoRoot.'/packages/*/database/migrations', GLOB_ONLYDIR)) === false ? [] : $entries) as $migDir) {
        $pkgDir = dirname(dirname($migDir));
        $migOwner = $packageDirs[$pkgDir] ?? basename($pkgDir);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migDir));

        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);

            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php')) {
                continue;
            }

            if (str_contains($file->getPathname(), '/tests/')) {
                continue;
            }

            $relPath = ltrim(str_replace($repoRoot.'/', '', $file->getPathname()), '/');
            $src = (string) file_get_contents($file->getPathname());

            foreach (extractReferencedTables($src) as $refTable) {
                $refOwner = $catalog[$refTable] ?? null;

                if ($refOwner === null || $refOwner === $migOwner) {
                    continue;
                }

                if (isAllowedPlatformReference($migOwner, $refTable)) {
                    continue;
                }

                $hits[] = [
                    'file' => $relPath,
                    'message' => "cross-package reference to '{$refTable}' (owner {$refOwner}) from migration package {$migOwner}",
                ];
            }
        }
    }

    return $hits;
}

function isAllowedPlatformReference(string $migrationOwner, string $referencedTable): bool
{
    if ($migrationOwner === CORE_PLATFORM_OWNER) {
        return false;
    }

    return in_array($referencedTable, PLATFORM_REFERENCE_TABLES, true);
}

/**
 * @return list<string>
 */
function extractReferencedTables(string $src): array
{
    $tables = [];

    if ((($qualityMatchCount = preg_match_all('/->constrained\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $src, $matches)) !== false && $qualityMatchCount > 0)) {
        $tables = [...$tables, ...$matches[1]];
    }

    if ((($qualityMatchCount = preg_match_all('/->on\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $src, $matches)) !== false && $qualityMatchCount > 0)) {
        $tables = [...$tables, ...$matches[1]];
    }

    return array_values(array_unique($tables));
}
