<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

// P6.3 — Foundation litmus (A29, D34). The file list is an explicit constant
// of the live Foundation map, not "every file except another zone".
// Condition 3 is fail-closed outside LITMUS_ELOQUENT. A fourth allowlist
// path needs a decision record. Condition 1 below the grep bar is a warning.
// An unobservable reference is unresolved, not a failure. Condition 5 above
// 200 lines outside Contracts, Enums, or Data is unresolved (P2 §7.1).

/**
 * Foundation paths. Mirrors the live map in docs/architecture/core-zones.md
 * after P6.1. The design baseline of 109 files is not this list.
 *
 * @var list<string>
 */
const LITMUS_FOUNDATION = [
    'packages/core/src/Messaging/PHPStan/DurablePayloadTypeChecker.php',
    'packages/core/src/Data/Laravel/SystemDataScopeAuthorization.php',
    'packages/core/src/Messaging/PHPStan/Rules/DurablePayloadPromotedPropertyRule.php',
    'packages/core/src/Data/Data/JsonDocument.php',
    'packages/core/src/Data/Laravel/JsonDocuments.php',
    'packages/core/src/Database/Laravel/HasConfiguredId.php',
    'packages/core/src/Data/Contracts/DataScopeAuthorizer.php',
    'packages/core/src/Data/Contracts/DataScopeCodec.php',
    'packages/core/src/Messaging/Contracts/DurablePayload.php',
    'packages/core/src/Modules/Contracts/ModuleRegistrar.php',
    'packages/core/src/Data/Data/DataScopeAccessDecision.php',
    'packages/core/src/Audit/Enums/ActorType.php',
    'packages/core/src/Database/Enums/IdStrategy.php',
    'packages/core/src/Messaging/Contracts/Cancellable.php',
    'packages/core/src/Messaging/Events/DomainEvent.php',
    'packages/core/src/Data/Exceptions/InvalidScopePayload.php',
    'packages/core/src/Database/Laravel/BaseModel.php',
    'packages/core/src/Messaging/PHPStan/Rules/DurablePayloadRule.php',
    'packages/core/src/Data/Internal/AllowAllDataScopeAuthorizer.php',
    'packages/core/src/Data/Internal/ArrayDataScopeCodec.php',
    'packages/core/src/Data/Internal/CallableDataScopeAuthorizer.php',
    'packages/core/src/Data/Data/DataScope.php',
    'packages/core/src/Data/Internal/DenyDataScopeAuthorizer.php',
    'packages/core/src/Data/Data/RecordRef.php',
    'packages/core/src/Data/Data/WriteToken.php',
    'packages/core/src/Database/Laravel/IdColumns.php',
    'packages/core/src/Time/Testing/FrozenClock.php',
    'packages/core/src/Time/Laravel/LaravelClock.php',
    'packages/core/src/Time/Laravel/SystemClock.php',
];

/**
 * Closed Eloquent/HTTP/Application exceptions. Exactly these three paths.
 *
 * @var list<string>
 */
const LITMUS_ELOQUENT = [
    'packages/core/src/Database/Laravel/BaseModel.php',
    'packages/core/src/Database/Laravel/HasConfiguredId.php',
    'packages/core/src/Database/Laravel/IdColumns.php',
];

/** @var list<string> */
const LITMUS_FORBIDDEN_SEGMENTS = [
    'Media',
    'Search',
    'Task',
    'Workflow',
    'Wakeup',
    'Panel',
    'Crm',
    'Notification',
];

test('foundation membership is an explicit constant of the live zone map', function (): void {
    $expected = LITMUS_FOUNDATION;
    $live = litmusLiveFoundationFiles();
    sort($expected);
    sort($live);

    expect($expected)->toBe($live)
        ->and(LITMUS_FOUNDATION)->not->toBe([]);
});

test('LITMUS_ELOQUENT is exactly the three identity paths', function (): void {
    expect(LITMUS_ELOQUENT)->toHaveCount(3)
        ->and(LITMUS_ELOQUENT[0])->toEndWith('Database/Laravel/BaseModel.php')
        ->and(LITMUS_ELOQUENT[1])->toEndWith('Database/Laravel/HasConfiguredId.php')
        ->and(LITMUS_ELOQUENT[2])->toEndWith('Database/Laravel/IdColumns.php');
});

test('foundation litmus hard conditions are green on the clean tree', function (): void {
    expect(litmusRl3Offenders())->toBe([])
        ->and(litmusNameOffenders())->toBe([])
        ->and(litmusNotesOffenders())->toBe([])
        ->and(litmusZoneOffenders())->toBe([]);
});

test('condition 1 below the grep bar warns and does not fail the gate', function (): void {
    expect(litmusTypeAdoption('packages/core/src/Data/Data/RecordRef.php', 'RecordRef'))->toBe('pass')
        ->and(litmusTypeAdoption('packages/core/src/Modules/Contracts/ModuleRegistrar.php', 'ModuleRegistrar'))->toBe('warning')
        ->and(litmusRl3Offenders())->toBe([]);
});

test('condition 5 above 200 lines outside Contracts Enums and Data is unresolved', function (): void {
    $rule = 'packages/core/src/Messaging/PHPStan/Rules/DurablePayloadRule.php';
    $absolute = litmusAbsolute($rule);
    $original = (string) file_get_contents($absolute);

    try {
        file_put_contents($absolute, $original.str_repeat("\n", 201));
        expect(litmusSizeStatus($rule))->toBe('unresolved');
    } finally {
        file_put_contents($absolute, $original);
    }

    expect(litmusSizeStatus($rule))->toBe('pass')
        ->and(litmusSizeStatus('packages/core/src/Database/Laravel/BaseModel.php'))->toBe('pass')
        ->and(litmusRl3Offenders())->toBe([])
        ->and(litmusNameOffenders())->toBe([]);
});

test('R-L3 planting in Foundation RecordRef names the file and Eloquent', function (): void {
    $relative = 'packages/core/src/Data/Data/RecordRef.php';
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $original = (string) file_get_contents($absolute);
    $needle = "declare(strict_types=1);\n";
    $count = 0;
    $planted = str_replace(
        $needle,
        $needle."\nuse Illuminate\\Database\\Eloquent\\Model;\n",
        $original,
        $count,
    );

    if ($count !== 1) {
        throw new RuntimeException('R-L3 planting could not insert a use into '.$relative);
    }

    file_put_contents($absolute, $planted);

    try {
        $hits = array_values(array_filter(
            litmusRl3Offenders(),
            static fn (string $line): bool => str_contains($line, 'RecordRef.php')
                && str_contains($line, 'Eloquent'),
        ));

        expect($hits)->not->toBe([])
            ->and($hits[0])->toContain('R-L3')
            ->and($hits[0])->toContain('RecordRef.php')
            ->and($hits[0])->toContain('Eloquent');
    } finally {
        file_put_contents($absolute, $original);
    }
});

/**
 * @return list<string>
 */
function litmusRl3Offenders(): array
{
    $offenders = [];

    foreach (LITMUS_FOUNDATION as $relative) {
        if (in_array($relative, LITMUS_ELOQUENT, true)) {
            continue;
        }

        foreach (litmusImports(litmusAbsolute($relative)) as $import) {
            if (! litmusImportForbidden($import)) {
                continue;
            }

            $offenders[] = 'R-L3 '.$relative.' imports '.$import;
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * @return list<string>
 */
function litmusNameOffenders(): array
{
    $offenders = [];

    foreach (LITMUS_FOUNDATION as $relative) {
        $text = (string) file_get_contents(litmusAbsolute($relative));
        $namespace = '';

        if (preg_match('/^namespace\s+([^;]+);/m', $text, $matches) === 1) {
            $namespace = $matches[1];
        }

        $types = litmusDeclaredTypes($text);

        if ($types === []) {
            $types = [basename($relative, '.php')];
        }

        foreach ($types as $type) {
            $segments = array_values(array_filter(explode('\\', $namespace.'\\'.$type)));

            foreach ($segments as $segment) {
                if (str_contains($segment, 'Audit') && $relative !== 'packages/core/src/Audit/Enums/ActorType.php') {
                    $offenders[] = $relative.' litmus-2 segment '.$segment;

                    continue;
                }

                foreach (LITMUS_FORBIDDEN_SEGMENTS as $term) {
                    if (str_contains($segment, $term)) {
                        $offenders[] = $relative.' litmus-2 segment '.$segment;

                        break;
                    }
                }
            }
        }
    }

    $offenders = array_values(array_unique($offenders));
    sort($offenders);

    return $offenders;
}

/**
 * @return list<string>
 */
function litmusNotesOffenders(): array
{
    $offenders = [];

    foreach (LITMUS_FOUNDATION as $relative) {
        $text = (string) file_get_contents(litmusAbsolute($relative));

        if (str_contains($text, 'product-only')) {
            $offenders[] = $relative.' litmus-4 product-only';
        }
    }

    return $offenders;
}

/**
 * @return list<string>
 */
function litmusZoneOffenders(): array
{
    $neighbors = [];

    foreach (glob(SourceScanner::repoRoot().'/packages/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = strtolower(basename($dir));

        if ($name !== 'core') {
            $neighbors[] = $name;
        }
    }

    if (! in_array('foundation', $neighbors, true)) {
        return [];
    }

    return array_map(
        static fn (string $path): string => $path.' litmus-6 zone Foundation matches a package',
        LITMUS_FOUNDATION,
    );
}

function litmusTypeAdoption(string $relative, string $short): string
{
    $sites = litmusDeclarationSites($short);

    if (count($sites) !== 1) {
        return 'unresolved';
    }

    $packages = [];
    $pattern = '/\b'.preg_quote($short, '/').'\b/';

    foreach (litmusSrcCorpus() as [$package, $body]) {
        if (preg_match($pattern, $body) === 1) {
            $packages[$package] = true;
        }
    }

    $bar = litmusAdoptionBar($relative);

    return count($packages) >= $bar ? 'pass' : 'warning';
}

function litmusSizeStatus(string $relative): string
{
    $lines = file(litmusAbsolute($relative));
    $count = is_array($lines) ? count($lines) : 0;
    $under = substr($relative, strlen('packages/core/src/'));
    $segments = explode('/', $under);

    if (in_array('Contracts', $segments, true)
        || in_array('Enums', $segments, true)
        || in_array('Data', $segments, true)
        || $count <= 200) {
        return 'pass';
    }

    return 'unresolved';
}

function litmusAdoptionBar(string $relative): int
{
    $under = substr($relative, strlen('packages/core/src/'));

    foreach (['Contracts/', 'Enums/', 'Exceptions/'] as $prefix) {
        if (str_starts_with($under, $prefix)) {
            return 2;
        }
    }

    return 3;
}

function litmusAbsolute(string $relative): string
{
    return SourceScanner::repoRoot().'/'.$relative;
}

/**
 * Files the live map places in Foundation. R-L3 iterates LITMUS_FOUNDATION;
 * this scan only fails the suite when that constant drifts.
 *
 * @return list<string>
 */
function litmusLiveFoundationFiles(): array
{
    $prefixes = ['Messaging/PHPStan/', 'Data/', 'Time/'];
    $named = [
        'Database/Laravel/HasConfiguredId.php',
        'Database/Laravel/IdColumns.php',
        'Data/Contracts/DataScopeAuthorizer.php',
        'Data/Contracts/DataScopeCodec.php',
        'Messaging/Contracts/DurablePayload.php',
        'Modules/Contracts/ModuleRegistrar.php',
        'Data/DataScopeAccessDecision.php',
        'Audit/Enums/ActorType.php',
        'Database/Enums/IdStrategy.php',
        'Messaging/Contracts/Cancellable.php',
        'Messaging/Events/DomainEvent.php',
        'Data/Exceptions/InvalidScopePayload.php',
        'Database/Laravel/BaseModel.php',
    ];
    $root = SourceScanner::packagePath('core', 'src');
    $prefix = SourceScanner::repoRoot().'/packages/core/src/';
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $under = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
        $matched = in_array($under, $named, true);

        if (! $matched) {
            foreach ($prefixes as $pathPrefix) {
                if (str_starts_with($under, $pathPrefix)) {
                    $matched = true;

                    break;
                }
            }
        }

        if ($matched) {
            $files[] = 'packages/core/src/'.$under;
        }
    }

    sort($files);

    return $files;
}

function litmusImportForbidden(string $import): bool
{
    return $import === 'Illuminate\\Database\\Eloquent'
        || str_starts_with($import, 'Illuminate\\Database\\Eloquent\\')
        || $import === 'Illuminate\\Http'
        || str_starts_with($import, 'Illuminate\\Http\\')
        || $import === 'Illuminate\\Foundation\\Application'
        || str_starts_with($import, 'Illuminate\\Foundation\\Application\\');
}

/**
 * @return list<string>
 */
function litmusImports(string $absolute): array
{
    $contents = (string) file_get_contents($absolute);
    preg_match_all('/^\s*use\s+(?:function\s+|const\s+)?([^;]+);/m', $contents, $matches);
    $imports = [];

    foreach ($matches[1] as $clause) {
        $clause = trim($clause);

        if (preg_match('/^(.+?)\\\\?\{(.+)\}$/', $clause, $parts) === 1) {
            $prefix = rtrim($parts[1], '\\');

            foreach (explode(',', $parts[2]) as $name) {
                $name = trim(explode(' as ', trim($name), 2)[0]);

                if ($name !== '') {
                    $imports[] = $prefix.'\\'.$name;
                }
            }

            continue;
        }

        $imports[] = trim(explode(' as ', $clause, 2)[0]);
    }

    return $imports;
}

/**
 * @return list<string>
 */
function litmusDeclaredTypes(string $text): array
{
    preg_match_all(
        '/^(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m',
        $text,
        $matches,
    );

    return $matches[1];
}

/**
 * @return list<string>
 */
function litmusDeclarationSites(string $short): array
{
    $sites = litmusDeclarationIndex();

    return $sites[$short] ?? [];
}

/**
 * @return array<string, list<string>>
 */
function litmusDeclarationIndex(): array
{
    static $index = null;

    if ($index !== null) {
        return $index;
    }

    $index = [];

    foreach (litmusSrcCorpus() as [$package, $body]) {
        foreach (litmusDeclaredTypes($body) as $type) {
            $index[$type][] = $package;
        }
    }

    return $index;
}

/**
 * @return list<array{0: string, 1: string}>
 */
function litmusSrcCorpus(): array
{
    static $corpus = null;

    if ($corpus !== null) {
        return $corpus;
    }

    $corpus = [];
    $root = SourceScanner::repoRoot().'/packages';

    foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $packageDir) {
        $src = $packageDir.'/src';

        if (! is_dir($src)) {
            continue;
        }

        $package = basename($packageDir);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $corpus[] = [$package, (string) file_get_contents($file->getPathname())];
        }
    }

    return $corpus;
}
