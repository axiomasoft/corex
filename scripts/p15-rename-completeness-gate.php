#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P15 rename completeness gate (P15.13 · P15.14).
 *
 * Scans tracked product paths for old tokens materialized in
 * p15-rename-completeness-manifest.json (derived from rename-registry outcomes).
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const DIAGNOSTIC = 'P15C';

$repoRoot = dirname(__DIR__);
$manifestPath = $repoRoot.'/scripts/p15-rename-completeness-manifest.json';
$registryPath = $repoRoot.'/docs/architecture/rename-registry.md';

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--emit-manifest') {
    emitManifest($repoRoot, $registryPath, $manifestPath);
    exit(EXIT_OK);
}

if (! is_file($manifestPath)) {
    fwrite(STDERR, "p15-rename-completeness-gate: missing manifest; run with --emit-manifest\n");
    exit(EXIT_CONFIG);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest) || ! isset($manifest['tokens']) || ! is_array($manifest['tokens'])) {
    fwrite(STDERR, "p15-rename-completeness-gate: invalid manifest JSON\n");
    exit(EXIT_CONFIG);
}

$scanRoots = $manifest['scan_roots'] ?? ['packages', 'scripts', 'tests', 'docs'];
$globalAllowlist = $manifest['global_allowlist_globs'] ?? [];
$files = listTrackedFiles($repoRoot, $scanRoots);
$violations = [];

foreach ($manifest['tokens'] as $entry) {
    if (! is_array($entry)) {
        fwrite(STDERR, "p15-rename-completeness-gate: invalid token entry\n");
        exit(EXIT_CONFIG);
    }

    $token = $entry['old'] ?? null;
    $item = $entry['item'] ?? 'P15';

    if (! is_string($token) || $token === '') {
        fwrite(STDERR, "p15-rename-completeness-gate: token missing old value\n");
        exit(EXIT_CONFIG);
    }

    $tokenAllowlist = array_merge(
        $globalAllowlist,
        is_array($entry['allowlist_globs'] ?? null) ? $entry['allowlist_globs'] : [],
    );

    foreach ($files as $relativePath) {
        if (pathMatchesAnyGlob($relativePath, $tokenAllowlist)) {
            continue;
        }

        $absolutePath = $repoRoot.'/'.$relativePath;

        if (! is_file($absolutePath)) {
            continue;
        }

        $contents = (string) file_get_contents($absolutePath);

        if (! tokenMatches($contents, $token, $entry)) {
            continue;
        }

        $line = lineNumberOfToken($contents, $token, $entry);
        $violations[] = sprintf(
            '%s: forbidden old token `%s` (%s) at %s:%d',
            DIAGNOSTIC,
            $token,
            $item,
            $relativePath,
            $line,
        );
    }
}

if ($violations !== []) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation."\n");
    }
    fwrite(STDERR, sprintf("p15-rename-completeness-gate: %d violation(s)\n", count($violations)));

    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, "p15-rename-completeness-gate: OK\n");
fwrite(STDOUT, sprintf("p15-rename-completeness-gate: tokens=%d files=%d\n", count($manifest['tokens']), count($files)));

exit(EXIT_OK);

/**
 * @return list<array{old: string, new: string, item: string, allowlist_globs: list<string>}>
 */
function buildTokenCatalog(string $registryPath): array
{
    $registryRows = parseRenameRegistry($registryPath);
    $tokens = [];

    foreach ($registryRows as $row) {
        $tokens[] = [
            'old' => $row['old'],
            'new' => $row['new'],
            'item' => $row['item'],
            'match' => matchModeForToken($row['old']),
            'allowlist_globs' => tokenAllowlistGlobs($row['old']),
            'line_exemptions' => lineExemptionsForToken($row['old']),
        ];
    }

    foreach (supplementalOutcomeTokens() as $row) {
        $row['match'] = $row['match'] ?? matchModeForToken($row['old']);
        $row['line_exemptions'] = $row['line_exemptions'] ?? lineExemptionsForToken($row['old']);
        $tokens[] = $row;
    }

    return dedupeTokens($tokens);
}

/**
 * @return list<array{old: string, new: string, item: string, allowlist_globs: list<string>}>
 */
function supplementalOutcomeTokens(): array
{
    return [
        [
            'old' => 'CompletionCaptureObserver',
            'new' => 'CaptureTaskCompletion',
            'item' => 'P15.6',
            'allowlist_globs' => [],
        ],
        [
            'old' => 'CoreX\\PubSub\\',
            'new' => 'CoreX\\Messaging\\',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Contracts\\Auditable',
            'new' => 'CoreX\\Audit\\Contracts\\Auditable',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Contracts\\AuditEntry',
            'new' => 'CoreX\\Audit\\Contracts\\AuditEntry',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Contracts\\AuditLogger',
            'new' => 'CoreX\\Audit\\Contracts\\AuditLogger',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Contracts\\AuditRetention',
            'new' => 'CoreX\\Audit\\Contracts\\AuditRetention',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Models\\AuditLog',
            'new' => 'CoreX\\Audit\\Internal\\AuditLog',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Views\\',
            'new' => 'CoreX\\SavedViews\\',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'CoreX\\Data\\DataScopeAccessDecision',
            'new' => 'CoreX\\Support\\Data\\DataScopeAccessDecision',
            'item' => 'P15.10',
            'allowlist_globs' => ['docs/architecture/core-zones.md'],
        ],
        [
            'old' => 'packages/api',
            'new' => 'packages/operations',
            'item' => 'P15.11',
            'allowlist_globs' => [],
        ],
        [
            'old' => 'packages/auth',
            'new' => 'packages/access',
            'item' => 'P15.12',
            'allowlist_globs' => ['scripts/anti-weakening-allowances.txt'],
        ],
    ];
}

/**
 * @param  list<array{old: string, new: string, item: string, allowlist_globs: list<string>}>  $tokens
 * @return list<array{old: string, new: string, item: string, allowlist_globs: list<string>}>
 */
function dedupeTokens(array $tokens): array
{
    $seen = [];
    $deduped = [];

    foreach ($tokens as $token) {
        $key = $token['old'].'|'.$token['item'];

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $deduped[] = $token;
    }

    return $deduped;
}

/**
 * @return list<array{old: string, new: string, item: string}>
 */
function parseRenameRegistry(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "p15-rename-completeness-gate: missing {$path}\n");
        exit(EXIT_CONFIG);
    }

    $rows = [];
    $itemByPackage = [
        'modules' => 'P15.1',
        'attributes' => 'P15.2',
        'search' => 'P15.3',
        'search-meilisearch' => 'P15.4',
        'transfer' => 'P15.5',
        'tasks-workflow' => 'P15.6',
        'wakeup' => 'P15.7',
        'api' => 'P15.8',
        'tenancy' => 'P15.9',
        'operations' => 'P15.11',
        'access' => 'P15.12',
    ];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (! str_starts_with($line, '| `')) {
            continue;
        }

        $parts = array_map('trim', explode('|', trim($line, '|')));

        if (count($parts) < 4) {
            continue;
        }

        $old = trim($parts[0], '` ');
        $new = trim($parts[1], '` ');
        $package = trim($parts[2], '` ');

        if ($old === 'Сейчас' || $old === ':--') {
            continue;
        }

        $item = $itemByPackage[$package] ?? 'P15';

        if ($package === 'api' && str_starts_with($old, 'corex/')) {
            $item = 'P15.11';
        }

        if ($package === 'auth' && str_starts_with($old, 'corex/')) {
            $item = 'P15.12';
        }

        $rows[] = [
            'old' => $old,
            'new' => $new,
            'item' => $item,
        ];
    }

    return $rows;
}

function emitManifest(string $repoRoot, string $registryPath, string $manifestPath): void
{
    $tokens = buildTokenCatalog($registryPath);
    $registryHash = hash_file('sha256', $registryPath);

    $manifest = [
        'schema_version' => 1,
        'registry_sha256' => $registryHash,
        'generated_by' => 'scripts/p15-rename-completeness-gate.php --emit-manifest',
        'scan_roots' => ['packages', 'scripts', 'tests', 'docs'],
        'global_allowlist_globs' => [
            'plans/**',
            'audits/**',
            'CHANGELOG.md',
            'packages/*/CHANGELOG.md',
            'docs/architecture/rename-registry.md',
            'docs/architecture/core-zones.md',
            'docs/audit-*',
            'scripts/naming-gate.php',
            'scripts/naming-gate-grandfather.json',
            'scripts/mutations/**',
            'scripts/p15-rename-completeness-manifest.json',
            'scripts/p15-rename-completeness-gate.php',
            'scripts/anti-weakening-allowances.txt',
        ],
        'tokens' => $tokens,
    ];

    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($encoded === false) {
        fwrite(STDERR, "p15-rename-completeness-gate: failed to encode manifest\n");
        exit(EXIT_CONFIG);
    }

    file_put_contents($manifestPath, $encoded."\n");
    fwrite(STDOUT, sprintf("p15-rename-completeness-gate: emitted %d tokens to %s\n", count($tokens), $manifestPath));
}

/**
 * @param  list<string>  $roots
 * @return list<string>
 */
function listTrackedFiles(string $repoRoot, array $roots): array
{
    $quotedRoots = implode(' ', array_map(static fn (string $root): string => escapeshellarg($root), $roots));
    $command = 'git -C '.escapeshellarg($repoRoot).' ls-files -- '.$quotedRoots;
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "p15-rename-completeness-gate: git ls-files failed\n");
        exit(EXIT_CONFIG);
    }

    $files = [];

    foreach ($output as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (str_ends_with($line, '.png') || str_ends_with($line, '.jpg') || str_ends_with($line, '.woff2')) {
            continue;
        }

        $files[] = $line;
    }

    sort($files);

    return $files;
}

/**
 * @param  list<string>  $globs
 */
function pathMatchesAnyGlob(string $path, array $globs): bool
{
    foreach ($globs as $glob) {
        if ($glob === $path) {
            return true;
        }

        if (str_ends_with($glob, '/**')) {
            $prefix = substr($glob, 0, -3);

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        if (str_contains($glob, '*')) {
            $pattern = '#^'.str_replace('\*\*', '.*', preg_quote($glob, '#')).'$#';
            $pattern = str_replace('\*', '[^/]*', $pattern);

            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param  array<string, mixed>  $entry
 */
function tokenMatches(string $contents, string $token, array $entry): bool
{
    $mode = is_string($entry['match'] ?? null) ? $entry['match'] : matchModeForToken($token);

    if ($mode !== 'word') {
        return str_contains($contents, $token);
    }

    $pattern = '/\b'.preg_quote($token, '/').'\b/';
    $exemptions = [];

    foreach ($entry['line_exemptions'] ?? [] as $needle) {
        if (is_string($needle) && $needle !== '') {
            $exemptions[] = $needle;
        }
    }

    if ($token === 'Dispatcher' && fileUsesIlluminateDispatcher($contents)) {
        return false;
    }

    foreach (explode("\n", $contents) as $line) {
        if (preg_match($pattern, $line) !== 1) {
            continue;
        }

        foreach ($exemptions as $needle) {
            if (str_contains($line, $needle)) {
                continue 2;
            }
        }

        return true;
    }

    return false;
}

function fileUsesIlluminateDispatcher(string $contents): bool
{
    return preg_match('/^use\s+Illuminate\\\\(?:Contracts\\\\Events|Events|Bus)\\\\Dispatcher\s*;/m', $contents) === 1;
}

function matchModeForToken(string $token): string
{
    if (str_contains($token, '::$') || str_contains($token, '/') || str_contains($token, '\\')) {
        return 'substring';
    }

    return 'word';
}

/**
 * @return list<string>
 */
/**
 * @return list<string>
 */
function tokenAllowlistGlobs(string $token): array
{
    return match ($token) {
        'Dep' => ['docs/architecture/naming.md'],
        default => [],
    };
}

/**
 * @return list<string>
 */
function lineExemptionsForToken(string $token): array
{
    return [];
}

/**
 * @param  array<string, mixed>  $entry
 */
function lineNumberOfToken(string $contents, string $token, array $entry): int
{
    $mode = is_string($entry['match'] ?? null) ? $entry['match'] : matchModeForToken($token);
    $pattern = $mode === 'word'
        ? '/\b'.preg_quote($token, '/').'\b/'
        : null;
    $exemptions = [];

    foreach ($entry['line_exemptions'] ?? [] as $needle) {
        if (is_string($needle) && $needle !== '') {
            $exemptions[] = $needle;
        }
    }

    if ($token === 'Dispatcher' && fileUsesIlluminateDispatcher($contents)) {
        return 0;
    }

    foreach (explode("\n", $contents) as $index => $line) {
        $matches = $pattern === null
            ? str_contains($line, $token)
            : preg_match($pattern, $line) === 1;

        if (! $matches) {
            continue;
        }

        foreach ($exemptions as $needle) {
            if (str_contains($line, $needle)) {
                continue 2;
            }
        }

        return $index + 1;
    }

    return 0;
}
