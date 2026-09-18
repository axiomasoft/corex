#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Executable package dependency graph gate (P12.3 · D41).
 *
 * Reads raw `use` import lines — not autoload-resolved classes — and applies:
 *   R1 undeclared cross-package dependency
 *   R2 foreign Internal namespace import
 *   R3 namespace ownership (with reservations + legacy splits from config)
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot.'/scripts/package-graph-gate.json';
$reportPath = $repoRoot.'/artifacts/P12-package-graph/report.json';

$config = loadConfig($configPath);
$packages = discoverPackages($repoRoot);
$classIndex = buildClassIndex($repoRoot, $packages);

$violations = [];
$legacySplits = [];

foreach ($packages as $dirName => $meta) {
    $srcDir = $repoRoot.'/packages/'.$dirName.'/src';

    if (! is_dir($srcDir)) {
        continue;
    }

    foreach (walkPhpFiles($srcDir) as $file) {
        $relativeFile = substr($file, strlen($repoRoot) + 1);
        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            continue;
        }

        foreach ($lines as $lineNumber => $line) {
            $import = parseUseImport($line);

            if ($import === null) {
                continue;
            }

            $ownerPackage = resolveOwner($import, $packages, $classIndex);

            if ($ownerPackage === null) {
                continue;
            }

            if ($ownerPackage === $meta['name']) {
                continue;
            }

            if (violatesForeignInternal($import, $meta['name'], $ownerPackage)) {
                $violations[] = violation(
                    rule: 'R2',
                    file: $relativeFile,
                    line: $lineNumber + 1,
                    import: $import,
                    owner: $ownerPackage,
                    message: 'foreign Internal import',
                );
            }

            if (violatesUndeclaredDependency(
                $meta,
                $ownerPackage,
                $relativeFile,
                $config['allowlist'],
            )) {
                $violations[] = violation(
                    rule: 'R1',
                    file: $relativeFile,
                    line: $lineNumber + 1,
                    import: $import,
                    owner: $ownerPackage,
                    message: 'undeclared cross-package dependency',
                );
            }
        }
    }
}

foreach (detectNamespaceOwnershipConflicts($classIndex, $packages, $config) as $conflict) {
    if ($conflict['kind'] === 'legacy') {
        $legacySplits[] = $conflict;

        continue;
    }

    $violations[] = violation(
        rule: 'R3',
        file: $conflict['file'],
        line: $conflict['line'],
        import: $conflict['prefix'],
        owner: implode(', ', $conflict['packages']),
        message: $conflict['message'],
    );
}

usort($violations, static fn (array $a, array $b): int => [$a['file'], $a['line'], $a['rule']] <=> [$b['file'], $b['line'], $b['rule']]);

$report = [
    'schema' => 'package-graph-report/1',
    'generated_at' => gmdate('c'),
    'legacy_namespace_splits' => array_map(
        static fn (array $split): array => [
            'prefix' => $split['prefix'],
            'packages' => $split['packages'],
            'adr' => $split['adr'],
            'expires_when' => $split['expires_when'],
            'reason' => $split['reason'],
            'class_counts' => $split['class_counts'],
        ],
        $legacySplits,
    ),
    'violations' => $violations,
    'summary' => [
        'packages_scanned' => count($packages),
        'classes_indexed' => count($classIndex),
        'violation_count' => count($violations),
        'legacy_split_count' => count($legacySplits),
    ],
];

writeReport($reportPath, $report);

foreach ($legacySplits as $split) {
    fwrite(STDERR, sprintf(
        "LEGACY NAMESPACE SPLIT: %s shared by [%s] — ADR %s; expires when: %s\n",
        $split['prefix'],
        implode(', ', $split['packages']),
        $split['adr'],
        $split['expires_when'],
    ));
}

foreach ($violations as $violation) {
    fwrite(STDERR, formatViolationLine($violation)."\n");
}

if ($violations !== []) {
    fwrite(STDERR, sprintf("package-graph-gate: %d violation(s)\n", count($violations)));
    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, "package-graph-gate: OK\n");
exit(EXIT_OK);

/**
 * @return array{allowlist: list<array<string, string>>, reservations: list<array<string, string>>, legacy_namespace_splits: list<array<string, mixed>>}
 */
function loadConfig(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "package-graph-gate: missing config {$path}\n");
        exit(EXIT_CONFIG);
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fwrite(STDERR, "package-graph-gate: invalid JSON in {$path}\n");
        exit(EXIT_CONFIG);
    }

    return [
        'allowlist' => $decoded['allowlist'] ?? [],
        'reservations' => $decoded['reservations'] ?? [],
        'legacy_namespace_splits' => $decoded['legacy_namespace_splits'] ?? [],
    ];
}

/**
 * @return array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>}>
 */
function discoverPackages(string $repoRoot): array
{
    $packages = [];

    foreach (glob($repoRoot.'/packages/*/composer.json') ?: [] as $composerPath) {
        $dirName = basename(dirname($composerPath));
        $composer = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($composer) || ! isset($composer['name'])) {
            continue;
        }

        $psr4 = [];
        foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $relativeDir) {
            $psr4[rtrim($prefix, '\\').'\\'] = $relativeDir;
        }

        $packages[$dirName] = [
            'name' => (string) $composer['name'],
            'dir' => $dirName,
            'require' => array_keys($composer['require'] ?? []),
            'psr4' => $psr4,
        ];
    }

    ksort($packages);

    return $packages;
}

/**
 * @param  array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>}>  $packages
 * @return array<string, string>
 */
function buildClassIndex(string $repoRoot, array $packages): array
{
    $index = [];

    foreach ($packages as $dirName => $meta) {
        $srcDir = $repoRoot.'/packages/'.$dirName.'/src';

        if (! is_dir($srcDir)) {
            continue;
        }

        foreach (walkPhpFiles($srcDir) as $file) {
            $contents = (string) file_get_contents($file);

            if (! preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
                continue;
            }

            $namespace = trim($namespaceMatch[1]).'\\';

            if (! preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $typeMatches)) {
                continue;
            }

            foreach ($typeMatches[1] as $shortName) {
                $index[$namespace.$shortName] = $meta['name'];
            }
        }
    }

    ksort($index);

    return $index;
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

function parseUseImport(string $line): ?string
{
    if (preg_match('/^\s*use\s+(?:function|const)\s+([^;{]+);/', $line, $matches) === 1) {
        return normalizeImport(trim($matches[1]));
    }

    if (preg_match('/^\s*use\s+([^;{]+);/', $line, $matches) !== 1) {
        return null;
    }

    $statement = trim($matches[1]);

    if (str_contains($statement, '{')) {
        return null;
    }

    return normalizeImport($statement);
}

function normalizeImport(string $statement): string
{
    $parts = array_map(trim(...), explode(',', $statement));

    return $parts[0];
}

/**
 * @param  array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>}>  $packages
 * @param  array<string, string>  $classIndex
 */
function resolveOwner(string $import, array $packages, array $classIndex): ?string
{
    if (isset($classIndex[$import])) {
        return $classIndex[$import];
    }

    $bestPrefix = '';
    $owner = null;

    foreach ($packages as $meta) {
        foreach ($meta['psr4'] as $prefix => $_relativeDir) {
            if (str_starts_with($import, $prefix) && strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
                $owner = $meta['name'];
            }
        }
    }

    if ($owner === null) {
        return null;
    }

    if (! str_starts_with($owner, 'corex/') && ! str_starts_with($owner, 'flex/')) {
        return null;
    }

    return $owner;
}

function violatesForeignInternal(string $import, string $fromPackage, string $ownerPackage): bool
{
    if ($fromPackage === $ownerPackage) {
        return false;
    }

    return preg_match('/\\\\Internal\\\\/', $import) === 1;
}

/**
 * @param  array{name: string, dir: string, require: list<string>, psr4: array<string, string>}  $fromMeta
 * @param  list<array<string, string>>  $allowlist
 */
function violatesUndeclaredDependency(array $fromMeta, string $toPackage, string $relativeFile, array $allowlist): bool
{
    if (in_array($toPackage, $fromMeta['require'], true)) {
        return false;
    }

    foreach ($allowlist as $entry) {
        if (($entry['from'] ?? '') !== $fromMeta['name']) {
            continue;
        }

        if (($entry['to'] ?? '') !== $toPackage) {
            continue;
        }

        if (($entry['guard_file'] ?? '') === $relativeFile) {
            return false;
        }
    }

    return true;
}

/**
 * @param  array<string, string>  $classIndex
 * @param  array<string, array{name: string, dir: string, require: list<string>, psr4: array<string, string>}>  $packages
 * @param  array{allowlist: list<array<string, string>>, reservations: list<array<string, string>>, legacy_namespace_splits: list<array<string, mixed>>}  $config
 * @return list<array<string, mixed>>
 */
function detectNamespaceOwnershipConflicts(array $classIndex, array $packages, array $config): array
{
    $conflicts = [];
    $seenLegacy = [];

    foreach ($config['legacy_namespace_splits'] as $entry) {
        $prefix = (string) ($entry['prefix'] ?? '');
        $expectedPackages = array_values($entry['packages'] ?? []);
        sort($expectedPackages);
        $actualPackages = packagesWithClassesUnderPrefix($prefix, $classIndex);
        sort($actualPackages);

        if ($actualPackages === [] || $actualPackages !== $expectedPackages) {
            continue;
        }

        $legacyKey = $prefix.'|'.implode(',', $actualPackages);

        if (isset($seenLegacy[$legacyKey])) {
            continue;
        }
        $seenLegacy[$legacyKey] = true;

        $conflicts[] = [
            'kind' => 'legacy',
            'prefix' => $prefix,
            'packages' => $actualPackages,
            'adr' => (string) ($entry['adr'] ?? ''),
            'expires_when' => (string) ($entry['expires_when'] ?? ''),
            'reason' => (string) ($entry['reason'] ?? ''),
            'class_counts' => classCountsUnderPrefix($prefix, $classIndex, $actualPackages),
        ];
    }

    $prefixClaims = [];
    foreach ($packages as $meta) {
        foreach ($meta['psr4'] as $prefix => $_relativeDir) {
            $prefixClaims[] = [
                'package' => $meta['name'],
                'dir' => $meta['dir'],
                'prefix' => $prefix,
            ];
        }
    }

    $seenConflicts = [];

    for ($i = 0; $i < count($prefixClaims); $i++) {
        for ($j = $i + 1; $j < count($prefixClaims); $j++) {
            $left = $prefixClaims[$i];
            $right = $prefixClaims[$j];

            if ($left['package'] === $right['package']) {
                continue;
            }

            $overlap = overlappingPsr4Prefix($left['prefix'], $right['prefix']);

            if ($overlap === null) {
                continue;
            }

            $owners = packagesWithClassesUnderPrefix($overlap, $classIndex);

            if (count($owners) < 2) {
                continue;
            }

            if (! in_array($left['package'], $owners, true) || ! in_array($right['package'], $owners, true)) {
                continue;
            }

            $conflictKey = $overlap.'|'.implode(',', $owners);

            if (isset($seenConflicts[$conflictKey])) {
                continue;
            }
            $seenConflicts[$conflictKey] = true;

            if (isPrefixCoveredByLegacy($overlap, $config['legacy_namespace_splits'], $owners)) {
                continue;
            }

            if (findReservation($overlap, $owners, $config['reservations']) !== null) {
                continue;
            }

            $conflicts[] = [
                'kind' => 'violation',
                'prefix' => $overlap,
                'packages' => $owners,
                'file' => 'packages/'.$left['dir'].'/composer.json',
                'line' => 1,
                'message' => 'namespace prefix owned by multiple packages without reservation',
            ];
        }
    }

    return $conflicts;
}

function overlappingPsr4Prefix(string $left, string $right): ?string
{
    if ($left === $right) {
        return $left;
    }

    if (str_starts_with($left, $right)) {
        return $left;
    }

    if (str_starts_with($right, $left)) {
        return $right;
    }

    return null;
}

/**
 * @param  array<string, string>  $classIndex
 * @return list<string>
 */
function packagesWithClassesUnderPrefix(string $prefix, array $classIndex): array
{
    $packages = [];

    foreach ($classIndex as $fqcn => $packageName) {
        $namespace = substr($fqcn, 0, (int) strrpos($fqcn, '\\') + 1);

        if (str_starts_with($namespace, $prefix)) {
            $packages[$packageName] = true;
        }
    }

    $result = array_keys($packages);
    sort($result);

    return $result;
}

/**
 * @param  list<string>  $packages
 * @param  array<string, string>  $classIndex
 * @return array<string, int>
 */
function classCountsUnderPrefix(string $prefix, array $classIndex, array $packages): array
{
    $counts = array_fill_keys($packages, 0);

    foreach ($classIndex as $fqcn => $packageName) {
        $namespace = substr($fqcn, 0, (int) strrpos($fqcn, '\\') + 1);

        if (str_starts_with($namespace, $prefix) && isset($counts[$packageName])) {
            $counts[$packageName]++;
        }
    }

    return $counts;
}

/**
 * @param  list<string>  $owners
 * @param  list<array<string, mixed>>  $legacySplits
 */
function isPrefixCoveredByLegacy(string $prefix, array $legacySplits, array $owners): bool
{
    foreach ($legacySplits as $entry) {
        $legacyPrefix = (string) ($entry['prefix'] ?? '');
        $legacyPackages = array_values($entry['packages'] ?? []);
        sort($legacyPackages);
        $sortedOwners = $owners;
        sort($sortedOwners);

        if ($sortedOwners !== $legacyPackages) {
            continue;
        }

        if (str_starts_with($prefix, $legacyPrefix) || str_starts_with($legacyPrefix, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * @param  list<string>  $packages
 * @param  list<array<string, string>>  $reservations
 */
function findReservation(string $prefix, array $packages, array $reservations): ?array
{
    foreach ($reservations as $reservation) {
        if (($reservation['prefix'] ?? '') !== $prefix) {
            continue;
        }

        $host = (string) ($reservation['host'] ?? '');
        $owner = (string) ($reservation['owner'] ?? '');

        if (in_array($host, $packages, true) && in_array($owner, $packages, true) && count($packages) === 2) {
            return $reservation;
        }
    }

    return null;
}

/**
 * @return array{rule: string, file: string, line: int, import: string, owner: string, message: string}
 */
function violation(string $rule, string $file, int $line, string $import, string $owner, string $message): array
{
    return compact('rule', 'file', 'line', 'import', 'owner', 'message');
}

function formatViolationLine(array $violation): string
{
    return sprintf(
        '%s:%d: %s [%s] → %s (%s)',
        $violation['file'],
        $violation['line'],
        $violation['import'],
        $violation['rule'],
        $violation['owner'],
        $violation['message'],
    );
}

function writeReport(string $path, array $report): void
{
    $dir = dirname($path);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        fwrite(STDERR, "package-graph-gate: cannot create report directory {$dir}\n");
        exit(EXIT_CONFIG);
    }

    file_put_contents(
        $path,
        json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );
}
