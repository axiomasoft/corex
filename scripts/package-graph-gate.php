#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\PackageGraph;

use CoreX\Attributes\Tooling\SurfaceReport;
use CoreX\Attributes\Tooling\SurfaceScanner;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Stringable;
use Throwable;

/**
 * Executable package dependency graph gate (P12.3 · D41).
 *
 * Reads static references (`use`, group-use, FQCN, extends/implements, traits) — not
 * autoload-resolved classes — and applies:
 *   R1 undeclared cross-package dependency
 *   R2 foreign Internal namespace import
 *   R3 namespace ownership (with reservations + legacy splits from config)
 *   R4 import outside source Api/Spi membership
 *   R4b source classification/type diagnostics and stale manifests
 *   R5 package-type dependency direction
 *   R6 forbidden package edge
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const COREX_SCHEMA_VERSION = 1;

/** @var list<string> */
const VALID_PACKAGE_TYPES = ['foundation', 'capability', 'integration', 'product', 'tooling', 'fixture'];

/** @var list<string> */
const VALID_STABILITY = ['stable', 'experimental', 'internal', 'deprecated', 'unreleased'];

/** @var array<string, int> */
const PACKAGE_TYPE_RANK = [
    'foundation' => 0,
    'capability' => 1,
    'integration' => 2,
    'product' => 3,
];

/** @var list<string> */
const PACKAGE_TYPES_SKIP_R5 = ['tooling', 'fixture'];

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot.'/scripts/package-graph-gate.json';
$reportPath = $repoRoot.'/artifacts/P12-package-graph/report.json';

// The whole-monorepo AST/ownership graph exceeds PHP's 128 MiB CLI default.
ini_set('memory_limit', '1G');
require $repoRoot.'/vendor/autoload.php';

$config = loadConfig($configPath, $repoRoot);
$packages = discoverPackages($repoRoot);

try {
    $surface = (new SurfaceScanner)->scan($repoRoot);
} catch (Throwable $error) {
    fwrite(STDERR, 'package-graph-gate: API-CONFIG '.$error->getMessage()."\n");
    exit(EXIT_CONFIG);
}
$classIndex = $surface->owners;

$violations = [];
$legacySplits = [];
$activeReservations = [];

foreach ($packages as $dirName => $meta) {
    $srcDir = $repoRoot.'/'.$meta['path'].'/src';

    if (! is_dir($srcDir)) {
        continue;
    }

    foreach (walkPhpFiles($srcDir) as $file) {
        $relativeFile = substr($file, strlen($repoRoot) + 1);

        foreach (extractReferences($file) as $ref) {
            $import = $ref['import'];
            $ownerPackage = resolveOwner($import, $packages, $classIndex);

            if ($ownerPackage === null) {
                continue;
            }

            if ($ownerPackage === $meta['name']) {
                continue;
            }

            $forbiddenEdge = forbiddenEdgeMessage($meta['name'], $ownerPackage, $config['forbidden_edges']);

            if ($forbiddenEdge !== null) {
                $violations[] = violation(
                    rule: 'R6',
                    file: $relativeFile,
                    line: $ref['line'],
                    import: $import,
                    owner: $ownerPackage,
                    message: $forbiddenEdge,
                );
            }

            if (violatesForeignInternal($import, $meta['name'], $ownerPackage)
                && ! isInternalCompositionEdge($repoRoot, $relativeFile, $import, $meta['name'], $ownerPackage, $config['internal_composition_edges'])) {
                $violations[] = violation(
                    rule: 'R2',
                    file: $relativeFile,
                    line: $ref['line'],
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
                $config['undeclared_dependency_exceptions'],
                $repoRoot,
            )) {
                $violations[] = violation(
                    rule: 'R1',
                    file: $relativeFile,
                    line: $ref['line'],
                    import: $import,
                    owner: $ownerPackage,
                    message: 'undeclared cross-package dependency',
                );
            }

            $r4Message = violatesPublicApi($import, $meta['name'], $ownerPackage, $surface->manifests);

            if ($r4Message !== null) {
                $violations[] = violation(
                    rule: 'R4',
                    file: $relativeFile,
                    line: $ref['line'],
                    import: $import,
                    owner: $ownerPackage,
                    message: sprintf('импорт непубличного %s из пакета %s', $import, $ownerPackage),
                );
            }

            $r5Message = violatesPackageTypeDirection($meta['name'], $ownerPackage, $packages);

            if ($r5Message !== null) {
                $violations[] = violation(
                    rule: 'R5',
                    file: $relativeFile,
                    line: $ref['line'],
                    import: $import,
                    owner: $ownerPackage,
                    message: $r5Message,
                );
            }
        }
    }

    foreach ($meta['require'] as $dependency) {
        $forbiddenEdge = forbiddenEdgeMessage($meta['name'], $dependency, $config['forbidden_edges']);

        if ($forbiddenEdge !== null) {
            $violations[] = violation(
                rule: 'R6',
                file: $meta['path'].'/composer.json',
                line: 1,
                import: $dependency,
                owner: $dependency,
                message: $forbiddenEdge,
            );
        }
    }
}

foreach ($surface->diagnostics as $diagnostic) {
    $rule = match ($diagnostic->code) {
        'API-IMPORT' => 'R4',
        'API-INTERNAL-TYPE' => 'R2',
        default => 'R4b',
    };
    $violations[] = violation($rule, $diagnostic->file, $diagnostic->line, $diagnostic->symbol,
        $surface->owners[$diagnostic->symbol] ?? '', $diagnostic->code.' '.$diagnostic->message);
}
$surfaceArtifacts = ['docs/reference/public-api.md' => $surface->documentation()];
foreach ($surface->manifests as $package => $manifest) {
    $surfaceArtifacts[$surface->packagePaths[$package].'/resources/contracts/php-api.json'] = SurfaceReport::encode($manifest);
}
foreach ($surfaceArtifacts as $relativeFile => $bytes) {
    $path = $repoRoot.'/'.$relativeFile;

    if (! is_file($path) || file_get_contents($path) !== $bytes) {
        $violations[] = violation('R4b', $relativeFile, 1, '', '', 'API-STALE generated surface');
    }
}

foreach (evaluateReservations($classIndex, $packages, $config['reservations']) as $evaluation) {
    if ($evaluation['kind'] === 'active') {
        $activeReservations[] = $evaluation;

        continue;
    }

    $violations[] = violation(
        rule: 'R3',
        file: $evaluation['file'],
        line: $evaluation['line'],
        import: $evaluation['prefix'],
        owner: $evaluation['owner'],
        message: $evaluation['message'],
    );
}

foreach (detectNamespaceOwnershipConflicts($classIndex, $packages, $config, $activeReservations) as $conflict) {
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
    'reservations' => array_map(
        static fn (array $reservation): array => [
            'prefix' => $reservation['prefix'],
            'host' => $reservation['host'],
            'owner' => $reservation['owner'],
            'reason' => $reservation['reason'],
            'class_counts' => $reservation['class_counts'],
        ],
        $activeReservations,
    ),
    'undeclared_dependency_exceptions' => array_map(
        static fn (array $entry): array => [
            'from' => $entry['from'],
            'to' => $entry['to'],
            'import_file' => $entry['import_file'],
            'adr' => $entry['adr'],
            'expires_when' => $entry['expires_when'],
            'reason' => $entry['reason'],
        ],
        $config['undeclared_dependency_exceptions'],
    ),
    'public_api_exceptions' => array_map(
        static fn (array $entry): array => [
            'from' => $entry['from'],
            'to' => $entry['to'],
            'import_file' => $entry['import_file'],
            'adr' => $entry['adr'],
            'expires_when' => $entry['expires_when'],
            'reason' => $entry['reason'],
        ],
        $config['public_api_exceptions'],
    ),
    'violations' => $violations,
    'summary' => [
        'packages_scanned' => count($packages),
        'classes_indexed' => count($classIndex),
        'violation_count' => count($violations),
        'legacy_split_count' => count($legacySplits),
        'reservation_count' => count($activeReservations),
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

foreach ($activeReservations as $reservation) {
    fwrite(STDERR, sprintf(
        "HOST-RESERVED NAMESPACE: %s host=%s owner=%s — %s; class_counts=%s\n",
        $reservation['prefix'],
        $reservation['host'],
        $reservation['owner'],
        $reservation['reason'],
        json_encode($reservation['class_counts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ));
}

foreach ($config['undeclared_dependency_exceptions'] as $exception) {
    fwrite(STDERR, sprintf(
        "UNDECLARED DEPENDENCY EXCEPTION: %s -> %s in %s — ADR %s; expires when: %s\n",
        scalarString($exception['from'] ?? ''),
        scalarString($exception['to'] ?? ''),
        scalarString($exception['import_file'] ?? ''),
        scalarString($exception['adr'] ?? ''),
        scalarString($exception['expires_when'] ?? ''),
    ));
}

foreach ($config['public_api_exceptions'] as $exception) {
    fwrite(STDERR, sprintf(
        "PUBLIC API EXCEPTION: %s -> %s in %s — ADR %s; expires when: %s\n",
        scalarString($exception['from'] ?? ''),
        scalarString($exception['to'] ?? ''),
        scalarString($exception['import_file'] ?? ''),
        scalarString($exception['adr'] ?? ''),
        scalarString($exception['expires_when'] ?? ''),
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
 * @return array{
 *     allowlist: list<array<string, mixed>>,
 *     undeclared_dependency_exceptions: list<array<string, string>>,
 *     public_api_exceptions: list<array<string, string>>,
 *     internal_composition_edges: list<array<string, string>>,
 *     reservations: list<array<string, string>>,
 *     legacy_namespace_splits: list<array<string, mixed>>,
 *     forbidden_edges: list<array<string, string>>
 * }
 */
function loadConfig(string $path, string $repoRoot): array
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

    $config = [
        'allowlist' => configRecords($decoded['allowlist'] ?? []),
        'undeclared_dependency_exceptions' => stringConfigRecords($decoded['undeclared_dependency_exceptions'] ?? []),
        'public_api_exceptions' => stringConfigRecords($decoded['public_api_exceptions'] ?? []),
        'internal_composition_edges' => stringConfigRecords($decoded['internal_composition_edges'] ?? []),
        'reservations' => stringConfigRecords($decoded['reservations'] ?? []),
        'legacy_namespace_splits' => configRecords($decoded['legacy_namespace_splits'] ?? []),
        'forbidden_edges' => stringConfigRecords($decoded['forbidden_edges'] ?? []),
    ];

    validateAllowlistEntries($config['allowlist'], $repoRoot);
    validateForbiddenEdges($config['forbidden_edges']);

    return $config;
}

/**
 * @return array<string, array{
 *     name: string,
 *     dir: string,
 *     path: string,
 *     require: list<string>,
 *     psr4: array<string, string>,
 *     package_type: string,
 *     stability: string
 * }>
 */
function discoverPackages(string $repoRoot): array
{
    $packages = [];

    foreach (array_merge((($entries = glob($repoRoot.'/packages/*/composer.json')) === false ? [] : $entries), (($entries = glob($repoRoot.'/products/*/composer.json')) === false ? [] : $entries)) as $composerPath) {
        $dirName = basename(dirname($composerPath));
        $composer = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($composer) || ! isset($composer['name'])) {
            continue;
        }

        if (! is_string($composer['name'])) {
            throw new RuntimeException('Invalid package name');
        }
        $autoload = $composer['autoload'] ?? [];
        $rawPsr4 = is_array($autoload) ? ($autoload['psr-4'] ?? []) : [];
        $requirements = $composer['require'] ?? [];

        if (! is_array($rawPsr4) || ! is_array($requirements)) {
            throw new RuntimeException('Invalid package autoload/requirements');
        }
        $psr4 = [];
        foreach ($rawPsr4 as $prefix => $relativeDir) {
            if (! is_string($prefix) || ! is_string($relativeDir)) {
                throw new RuntimeException('Invalid package PSR-4 mapping');
            }
            $psr4[rtrim($prefix, '\\').'\\'] = $relativeDir;
        }
        $requires = [];
        foreach ($requirements as $name => $constraint) {
            if (! is_string($name) || ! is_string($constraint)) {
                throw new RuntimeException('Invalid package requirement');
            }
            $requires[] = $name;
        }

        $corex = parseExtraCorex($composer, $composerPath);

        $packages[$dirName] = [
            'path' => substr(dirname($composerPath), strlen($repoRoot) + 1),
            'name' => $composer['name'],
            'dir' => $dirName,
            'require' => $requires,
            'psr4' => $psr4,
            'package_type' => $corex['package_type'],
            'stability' => $corex['stability'],
        ];
    }

    ksort($packages);

    return $packages;
}

/** @return list<string> */
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
 * @return list<array{import: string, line: int, via: string}>
 */
function extractReferences(string $file): array
{
    $code = file_get_contents($file);

    if ($code === false) {
        return [];
    }

    $tokens = token_get_all($code, TOKEN_PARSE);
    $useTable = [];
    $namespace = '';
    $refs = [];
    $count = count($tokens);
    $depth = 0;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_string($token)) {
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }

            continue;
        }

        if ($token[0] === T_NAMESPACE && $depth === 0) {
            $namespace = readName($tokens, $i);

            continue;
        }

        if ($token[0] === T_USE) {
            if (isClosureUse($tokens, $i)) {
                continue;
            }

            $line = $token[2];

            foreach (readUseClause($tokens, $i, $line) as $clause) {
                $fqcn = $depth === 0
                    ? $clause['fqcn']
                    : resolveName($clause['fqcn'], $namespace, $useTable);

                if ($depth === 0) {
                    $useTable[$clause['alias']] = $clause['fqcn'];
                }

                $refs[] = [
                    'import' => $fqcn,
                    'line' => $clause['line'],
                    'via' => 'use',
                ];
            }

            continue;
        }

        if (in_array($token[0], [T_EXTENDS, T_IMPLEMENTS], true)) {
            foreach (readHierarchyNames($tokens, $i) as $entry) {
                $refs[] = [
                    'import' => resolveName($entry['name'], $namespace, $useTable),
                    'line' => $entry['line'],
                    'via' => 'extends',
                ];
            }

            continue;
        }

        if ($token[0] === T_NAME_FULLY_QUALIFIED) {
            $refs[] = [
                'import' => ltrim($token[1], '\\'),
                'line' => $token[2],
                'via' => 'fqcn',
            ];
        }
    }

    return uniqueRefs($refs);
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return list<array{fqcn: string, alias: string, line: int}>
 */
function readUseClause(array $tokens, int &$i, int $line): array
{
    $clauses = [];
    $i = skipWhitespace($tokens, $i + 1);

    if (isToken($tokens, $i, T_FUNCTION) || isToken($tokens, $i, T_CONST)) {
        advanceToSemicolon($tokens, $i);

        return [];
    }

    while ($i < count($tokens)) {
        $pairs = parseOneUseClause($tokens, $i);

        foreach ($pairs as $alias => $fqcn) {
            $clauses[] = [
                'fqcn' => $fqcn,
                'alias' => $alias,
                'line' => $line,
            ];
        }

        $i = skipWhitespace($tokens, $i);

        if (tokenText($tokens, $i) === ',') {
            $i++;

            continue;
        }

        if (tokenText($tokens, $i) === ';') {
            $i++;

            break;
        }

        break;
    }

    return $clauses;
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array<string, string>
 */
function parseOneUseClause(array $tokens, int &$i): array
{
    $i = skipWhitespace($tokens, $i);
    $prefix = readQualifiedName($tokens, $i);
    $i = skipWhitespace($tokens, $i);
    $next = tokenText($tokens, $i);

    if ($next === ';' || $next === ',') {
        $fqcn = ltrim($prefix, '\\');
        $short = shortClassName($fqcn);

        return [$short => $fqcn];
    }

    if (tokenText($tokens, $i) === '{') {
        $i++;
        $imports = [];

        while ($i < count($tokens) && tokenText($tokens, $i) !== '}') {
            $i = skipWhitespace($tokens, $i);

            if (tokenText($tokens, $i) === '}') {
                break;
            }

            $imports = array_merge($imports, readUseEntry($tokens, $i, $prefix));
            $i = skipWhitespace($tokens, $i);

            if (tokenText($tokens, $i) === ',') {
                $i++;
            }
        }

        if (tokenText($tokens, $i) === '}') {
            $i++;
        }

        return $imports;
    }

    return readUseEntry($tokens, $i, $prefix);
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array<string, string>
 */
function readUseEntry(array $tokens, int &$i, string $prefix): array
{
    $nameParts = [];

    while ($i < count($tokens)) {
        if (isToken($tokens, $i, T_WHITESPACE)) {
            $i++;

            continue;
        }

        $text = tokenText($tokens, $i);

        if ($text === null) {
            $i++;

            continue;
        }

        if ($text === ',' || $text === ';' || $text === '}') {
            break;
        }

        if ($text === 'as') {
            $i++;
            $i = skipWhitespace($tokens, $i);
            $alias = tokenText($tokens, $i);
            $short = implode('', $nameParts);
            $fqcn = combineFqcn($prefix, $short);
            $i++;

            return [$alias ?? shortClassName($fqcn) => $fqcn];
        }

        $nameParts[] = $text;
        $i++;
    }

    $short = implode('', $nameParts);

    if ($short === '') {
        return [];
    }

    $fqcn = combineFqcn($prefix, $short);

    return [shortClassName($fqcn) => $fqcn];
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return list<array{name: string, line: int}>
 */
function readHierarchyNames(array $tokens, int $keywordIndex): array
{
    $keyword = $tokens[$keywordIndex];
    $line = is_array($keyword) ? $keyword[2] : 0;
    $i = skipWhitespace($tokens, $keywordIndex + 1);
    $names = [];

    while ($i < count($tokens)) {
        $i = skipWhitespace($tokens, $i);
        $text = tokenText($tokens, $i);

        if ($text === '{' || $text === null) {
            break;
        }

        $name = readQualifiedName($tokens, $i);

        if ($name === '') {
            break;
        }

        $names[] = ['name' => $name, 'line' => $line];
        $i = skipWhitespace($tokens, $i);

        if (tokenText($tokens, $i) === ',') {
            $i++;

            continue;
        }

        break;
    }

    return $names;
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function readName(array $tokens, int $keywordIndex): string
{
    $i = skipWhitespace($tokens, $keywordIndex + 1);

    if (tokenText($tokens, $i) === '{') {
        $parts = [];
        $i++;

        while ($i < count($tokens)) {
            if (tokenText($tokens, $i) === '}') {
                break;
            }

            $text = tokenText($tokens, $i);

            if ($text !== null && $text !== ';') {
                $parts[] = $text;
            }

            $i++;
        }

        return rtrim(implode('', $parts), '\\');
    }

    return rtrim(readQualifiedName($tokens, $i), '\\');
}

/** @param array<string,string> $useTable */
function resolveName(string $name, string $namespace, array $useTable): string
{
    if (str_starts_with($name, '\\')) {
        return ltrim($name, '\\');
    }

    if (isset($useTable[$name])) {
        return $useTable[$name];
    }

    if (str_contains($name, '\\')) {
        return $name;
    }

    if ($namespace === '') {
        return $name;
    }

    return $namespace.'\\'.$name;
}

/**
 * @param  list<array{import: string, line: int, via: string}>  $refs
 * @return list<array{import: string, line: int, via: string}>
 */
function uniqueRefs(array $refs): array
{
    $seen = [];
    $unique = [];

    foreach ($refs as $ref) {
        $key = $ref['import'];

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $unique[] = [
            'import' => $ref['import'],
            'line' => $ref['line'],
            'via' => $ref['via'],
        ];
    }

    return $unique;
}

function combineFqcn(string $prefix, string $short): string
{
    $prefix = rtrim($prefix, '\\');

    if ($prefix === '') {
        return ltrim($short, '\\');
    }

    if ($short === '') {
        return ltrim($prefix, '\\');
    }

    return ltrim($prefix.'\\'.$short, '\\');
}

function shortClassName(string $fqcn): string
{
    $pos = strrpos($fqcn, '\\');

    if ($pos === false) {
        return $fqcn;
    }

    return substr($fqcn, $pos + 1);
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function readQualifiedName(array $tokens, int &$i): string
{
    $parts = [];

    while ($i < count($tokens)) {
        if (isToken($tokens, $i, T_WHITESPACE)) {
            $i++;

            continue;
        }

        $text = tokenText($tokens, $i);

        if ($text === null) {
            $i++;

            continue;
        }

        if (in_array($text, ['{', ',', ';', 'as', '}'], true)) {
            break;
        }

        $parts[] = $text;
        $i++;
    }

    return implode('', $parts);
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function advanceToSemicolon(array $tokens, int &$i): void
{
    while ($i < count($tokens) && tokenText($tokens, $i) !== ';') {
        $i++;
    }

    if (tokenText($tokens, $i) === ';') {
        $i++;
    }
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function skipWhitespace(array $tokens, int $i): int
{
    while ($i < count($tokens) && isToken($tokens, $i, T_WHITESPACE)) {
        $i++;
    }

    return $i;
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function tokenText(array $tokens, int $i): ?string
{
    if (! isset($tokens[$i])) {
        return null;
    }

    if (is_string($tokens[$i])) {
        return $tokens[$i];
    }

    return $tokens[$i][1];
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function isToken(array $tokens, int $i, int $type): bool
{
    return isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === $type;
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function isClosureUse(array $tokens, int $useIndex): bool
{
    $j = skipWhitespace($tokens, $useIndex + 1);

    return tokenText($tokens, $j) === '(';
}

/**
 * @param  array<string, array{name: string, dir: string, path: string, require: list<string>, psr4: array<string, string>, package_type: string, stability: string}>  $packages
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

    if (! str_starts_with($owner, 'axiomasoft/corex') && ! str_starts_with($owner, 'flex/')) {
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
 * @param  array<mixed>  $composer
 * @return array{package_type: string, stability: string}
 */
function parseExtraCorex(array $composer, string $composerPath): array
{
    $relativePath = str_contains($composerPath, '/packages/')
        ? 'packages/'.basename(dirname($composerPath)).'/composer.json'
        : $composerPath;
    $extra = $composer['extra'] ?? null;
    $corex = is_array($extra) ? ($extra['corex'] ?? null) : null;

    if (! is_array($corex)) {
        fwrite(STDERR, "package-graph-gate: missing or invalid extra.corex in {$relativePath}\n");
        exit(EXIT_CONFIG);
    }

    $schemaVersion = $corex['schema-version'] ?? null;

    if (! is_int($schemaVersion) || $schemaVersion !== COREX_SCHEMA_VERSION) {
        fwrite(STDERR, "package-graph-gate: invalid extra.corex.schema-version in {$relativePath}\n");
        exit(EXIT_CONFIG);
    }

    $packageType = $corex['package-type'] ?? null;

    if (! is_string($packageType) || ! in_array($packageType, VALID_PACKAGE_TYPES, true)) {
        fwrite(STDERR, "package-graph-gate: invalid extra.corex.package-type in {$relativePath}\n");
        exit(EXIT_CONFIG);
    }

    $stability = $corex['stability'] ?? null;

    if (! is_string($stability) || ! in_array($stability, VALID_STABILITY, true)) {
        fwrite(STDERR, "package-graph-gate: invalid extra.corex.stability in {$relativePath}\n");
        exit(EXIT_CONFIG);
    }

    return [
        'package_type' => $packageType,
        'stability' => $stability,
    ];
}

/** @param array<string, array{schema: string, package: string, symbols: array<string, array<string, mixed>>}> $manifests */
function violatesPublicApi(string $import, string $fromPackage, string $ownerPackage, array $manifests): ?string
{
    if ($fromPackage === $ownerPackage || violatesForeignInternal($import, $fromPackage, $ownerPackage)) {
        return null;
    }

    return isset($manifests[$ownerPackage]['symbols'][$import]) ? null : 'non-public symbol';
}

/**
 * @param  array<string, array{name: string, dir: string, path: string, require: list<string>, psr4: array<string, string>, package_type: string, stability: string}>  $packages
 */
function violatesPackageTypeDirection(string $fromPackage, string $toPackage, array $packages): ?string
{
    $fromMeta = null;
    $toMeta = null;

    foreach ($packages as $meta) {
        if ($meta['name'] === $fromPackage) {
            $fromMeta = $meta;
        }

        if ($meta['name'] === $toPackage) {
            $toMeta = $meta;
        }
    }

    if ($fromMeta === null || $toMeta === null) {
        return null;
    }

    $fromType = $fromMeta['package_type'];
    $toType = $toMeta['package_type'];

    if (in_array($fromType, PACKAGE_TYPES_SKIP_R5, true) || in_array($toType, PACKAGE_TYPES_SKIP_R5, true)) {
        return null;
    }

    if (! isset(PACKAGE_TYPE_RANK[$fromType]) || ! isset(PACKAGE_TYPE_RANK[$toType])) {
        return sprintf('%s (missing package-type) не вправе зависеть от %s (%s)', $fromPackage, $toPackage, $toType);
    }

    if (PACKAGE_TYPE_RANK[$fromType] >= PACKAGE_TYPE_RANK[$toType]) {
        return null;
    }

    return sprintf(
        '%s (%s) не вправе зависеть от %s (%s)',
        $fromPackage,
        $fromType,
        $toPackage,
        $toType,
    );
}

/** @param list<array<string, string>> $edges */
function forbiddenEdgeMessage(string $fromPackage, string $toPackage, array $edges): ?string
{
    foreach ($edges as $edge) {
        if (($edge['from'] ?? '') === $fromPackage && ($edge['to'] ?? '') === $toPackage) {
            return $fromPackage.' → '.$toPackage;
        }
    }

    return null;
}

/** @param list<array<string, string>> $edges */
function validateForbiddenEdges(array $edges): void
{
    foreach ($edges as $edge) {
        if (($edge['from'] ?? '') === '' || ($edge['to'] ?? '') === '' || ($edge['reason'] ?? '') === '') {
            fwrite(STDERR, "package-graph-gate: forbidden edge requires from, to and reason\n");
            exit(EXIT_CONFIG);
        }
    }
}

/**
 * @param  array{name: string, dir: string, path: string, require: list<string>, psr4: array<string, string>, package_type: string, stability: string}  $fromMeta
 * @param  list<array<string, mixed>>  $allowlist
 * @param  list<array<string, string>>  $exceptions
 */
function violatesUndeclaredDependency(
    array $fromMeta,
    string $toPackage,
    string $relativeFile,
    array $allowlist,
    array $exceptions,
    string $repoRoot,
): bool {
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

        if (($entry['import_file'] ?? '') !== $relativeFile) {
            continue;
        }

        if (allowlistGuardIsValid($entry, $repoRoot)) {
            return false;
        }
    }

    foreach ($exceptions as $entry) {
        if (($entry['from'] ?? '') !== $fromMeta['name']) {
            continue;
        }

        if (($entry['to'] ?? '') !== $toPackage) {
            continue;
        }

        if (($entry['import_file'] ?? '') === $relativeFile) {
            return false;
        }
    }

    return true;
}

/**
 * @param  list<array<string, mixed>>  $allowlist
 */
function validateAllowlistEntries(array $allowlist, string $repoRoot): void
{
    foreach ($allowlist as $entry) {
        $guardFile = scalarString($entry['guard_file'] ?? '');
        $importFile = scalarString($entry['import_file'] ?? '');
        $patterns = $entry['guard_contains'] ?? [];

        if ($guardFile === '' || $importFile === '' || ! is_file($repoRoot.'/'.$guardFile)) {
            fwrite(STDERR, sprintf(
                "package-graph-gate: invalid allowlist entry for %s -> %s (%s)\n",
                scalarString($entry['from'] ?? ''),
                scalarString($entry['to'] ?? ''),
                $importFile,
            ));
            exit(EXIT_CONFIG);
        }

        if (! is_array($patterns) || $patterns === []) {
            fwrite(STDERR, sprintf(
                "package-graph-gate: missing guard_contains for %s -> %s (%s)\n",
                scalarString($entry['from'] ?? ''),
                scalarString($entry['to'] ?? ''),
                $importFile,
            ));
            exit(EXIT_CONFIG);
        }
    }
}

/**
 * @param  array<string, mixed>  $entry
 */
function allowlistGuardIsValid(array $entry, string $repoRoot): bool
{
    $guardFile = scalarString($entry['guard_file'] ?? '');
    $guardPath = $repoRoot.'/'.$guardFile;

    if ($guardFile === '' || ! is_file($guardPath)) {
        return false;
    }

    $contents = (string) file_get_contents($guardPath);
    $patterns = $entry['guard_contains'] ?? [];

    if (! is_array($patterns) || $patterns === []) {
        return false;
    }

    return array_all($patterns, fn ($pattern) => ! (! is_string($pattern) || ! str_contains($contents, $pattern)));
}

/**
 * @param  array<string, string>  $classIndex
 * @param  array<string, array{name: string, dir: string, path: string, require: list<string>, psr4: array<string, string>, package_type: string, stability: string}>  $packages
 * @param  list<array<string, string>>  $reservations
 * @return list<array{kind: 'active', prefix: string, host: string, owner: string, reason: string, class_counts: array<string,int>}|array{kind: 'violation', prefix: string, file: string, line: int, owner: string, message: string}>
 */
function evaluateReservations(array $classIndex, array $packages, array $reservations): array
{
    $evaluations = [];
    $packageByName = [];

    foreach ($packages as $meta) {
        $packageByName[$meta['name']] = $meta;
    }

    foreach ($reservations as $reservation) {
        $prefix = $reservation['prefix'] ?? '';
        $host = $reservation['host'] ?? '';
        $owner = $reservation['owner'] ?? '';
        $reason = $reservation['reason'] ?? '';
        $hostMeta = $packageByName[$host] ?? null;
        $ownerMeta = $packageByName[$owner] ?? null;

        if ($prefix === '' || $hostMeta === null || $ownerMeta === null) {
            $evaluations[] = [
                'kind' => 'violation',
                'prefix' => $prefix,
                'file' => 'scripts/package-graph-gate.json',
                'line' => 1,
                'owner' => $host,
                'message' => 'reservation references unknown host or owner package',
            ];

            continue;
        }

        $classCounts = classCountsUnderPrefix($prefix, $classIndex, [$host, $owner]);
        $hostClassCount = $classCounts[$host] ?? 0;
        $ownerClassCount = $classCounts[$owner] ?? 0;
        $ownerRequiresHost = in_array($host, $ownerMeta['require'], true);

        if ($hostClassCount === 0 && $ownerClassCount > 0 && $ownerRequiresHost) {
            $evaluations[] = [
                'kind' => 'active',
                'prefix' => $prefix,
                'host' => $host,
                'owner' => $owner,
                'reason' => $reason,
                'class_counts' => $classCounts,
            ];

            continue;
        }

        $messages = [];

        if ($hostClassCount > 0) {
            $messages[] = 'host defines classes under reserved prefix';
        }

        if ($ownerClassCount === 0) {
            $messages[] = 'owner defines zero classes under reserved prefix';
        }

        if (! $ownerRequiresHost) {
            $messages[] = 'owner does not require host in composer.json require';
        }

        $evaluations[] = [
            'kind' => 'violation',
            'prefix' => $prefix,
            'file' => $ownerMeta['path'].'/composer.json',
            'line' => 1,
            'owner' => $host.', '.$owner,
            'message' => 'host-reserved namespace conditions not met: '.implode('; ', $messages),
        ];
    }

    return $evaluations;
}

/**
 * @param  array<string, string>  $classIndex
 * @param  array<string, array{name: string, dir: string, path: string, require: list<string>, psr4: array<string, string>, package_type: string, stability: string}>  $packages
 * @param  array{
 *     allowlist: list<array<string, mixed>>,
 *     undeclared_dependency_exceptions: list<array<string, string>>,
 *     public_api_exceptions: list<array<string, string>>,
 *     internal_composition_edges: list<array<string, string>>,
 *     reservations: list<array<string, string>>,
 *     legacy_namespace_splits: list<array<string, mixed>>,
 *     forbidden_edges: list<array<string, string>>
 * }  $config
 * @param  list<array{kind: 'active', prefix: string, host: string, owner: string, reason: string, class_counts: array<string,int>}>  $activeReservations
 * @return list<array{kind: 'legacy', prefix: string, packages: list<string>, adr: string, expires_when: string, reason: string, class_counts: array<string,int>}|array{kind: 'violation', prefix: string, packages: list<string>, file: string, line: int, message: string}>
 */
function detectNamespaceOwnershipConflicts(array $classIndex, array $packages, array $config, array $activeReservations): array
{
    $conflicts = [];
    $seenLegacy = [];

    foreach ($config['legacy_namespace_splits'] as $entry) {
        $prefix = scalarString($entry['prefix'] ?? '');
        $expected = $entry['packages'] ?? [];
        assert(is_array($expected));
        $expectedPackages = array_values($expected);
        foreach ($expectedPackages as $name) {
            assert(is_string($name));
        }
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
            'adr' => scalarString($entry['adr'] ?? ''),
            'expires_when' => scalarString($entry['expires_when'] ?? ''),
            'reason' => scalarString($entry['reason'] ?? ''),
            'class_counts' => classCountsUnderPrefix($prefix, $classIndex, $actualPackages),
        ];
    }

    $prefixClaims = [];
    foreach ($packages as $meta) {
        foreach ($meta['psr4'] as $prefix => $_relativeDir) {
            $prefixClaims[] = [
                'package' => $meta['name'],
                'dir' => $meta['dir'],
                'path' => $meta['path'],
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

            if (isPrefixCoveredByActiveReservation($overlap, $activeReservations)) {
                continue;
            }

            $conflicts[] = [
                'kind' => 'violation',
                'prefix' => $overlap,
                'packages' => $owners,
                'file' => $left['path'].'/composer.json',
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
        $legacyPrefix = scalarString($entry['prefix'] ?? '');
        $expected = $entry['packages'] ?? [];
        assert(is_array($expected));
        $legacyPackages = array_values($expected);
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
 * @param  list<array{kind: 'active', prefix: string, host: string, owner: string, reason: string, class_counts: array<string,int>}>  $activeReservations
 */
function isPrefixCoveredByActiveReservation(string $prefix, array $activeReservations): bool
{
    foreach ($activeReservations as $reservation) {
        $reservedPrefix = $reservation['prefix'];

        if ($prefix === $reservedPrefix || str_starts_with($prefix, $reservedPrefix) || str_starts_with($reservedPrefix, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{rule: string, file: string, line: int, import: string, owner: string, message: string}
 */
function violation(string $rule, string $file, int $line, string $import, string $owner, string $message): array
{
    return compact('rule', 'file', 'line', 'import', 'owner', 'message');
}

/** @param array{rule: string, file: string, line: int, import: string, owner: string, message: string} $violation */
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

/** @param array<string, mixed> $report */
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

/**
 * Exact provider-composition edges during an accepted ownership transfer.
 * The old target must still physically exist; moving it expires the allowance.
 *
 * @param  list<array<string, string>>  $edges
 */
function isInternalCompositionEdge(string $root, string $file, string $symbol, string $from, string $to, array $edges): bool
{
    foreach ($edges as $edge) {
        if (($edge['from'] ?? null) !== $from || ($edge['to'] ?? null) !== $to
            || ($edge['import_file'] ?? null) !== $file || ($edge['symbol'] ?? null) !== $symbol) {
            continue;
        }

        $target = $edge['target_file'] ?? '';

        if ($target !== '' && str_starts_with($target, 'packages/') && ! str_contains($target, '..') && is_file($root.'/'.$target)) {
            return true;
        }
    }

    return false;
}

/** @return list<array<string, mixed>> */
function configRecords(mixed $value): array
{
    if (! is_array($value) || ! array_is_list($value)) {
        fwrite(STDERR, "package-graph-gate: config records must be a list\n");
        exit(EXIT_CONFIG);
    }

    $records = [];
    foreach ($value as $record) {
        if (! is_array($record)) {
            fwrite(STDERR, "package-graph-gate: config record must be an object\n");
            exit(EXIT_CONFIG);
        }
        $fields = [];
        foreach ($record as $key => $field) {
            if (! is_string($key)) {
                fwrite(STDERR, "package-graph-gate: config field must have a string key\n");
                exit(EXIT_CONFIG);
            }
            $fields[$key] = $field;
        }
        $records[] = $fields;
    }

    return $records;
}

/** @return list<array<string, string>> */
function stringConfigRecords(mixed $value): array
{
    $records = [];
    foreach (configRecords($value) as $record) {
        $fields = [];
        foreach ($record as $key => $field) {
            if (! is_string($field)) {
                fwrite(STDERR, "package-graph-gate: config field must be a string\n");
                exit(EXIT_CONFIG);
            }
            $fields[$key] = $field;
        }
        $records[] = $fields;
    }

    return $records;
}

function scalarString(mixed $value): string
{
    if (! is_scalar($value) && $value !== null && ! $value instanceof Stringable) {
        throw new RuntimeException('Expected scalar config value');
    }

    return (string) $value;
}
