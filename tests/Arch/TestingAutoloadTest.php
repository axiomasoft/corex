<?php

declare(strict_types=1);

use CoreX\Testing\CoreXTestCase;
use CoreX\Tests\Support\SourceScanner;

// P16.10 — D33/D94 closed. CoreX\Testing is dev-autoload only.
// Production src must not import the retired harness namespaces.

test('CoreX\\Testing is dev-autoload only and production src has no harness files', function (): void {
    $root = SourceScanner::repoRoot();
    $core = json_decode(
        (string) file_get_contents($root.'/packages/core/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $monorepo = json_decode(
        (string) file_get_contents($root.'/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($core['autoload']['psr-4']['CoreX\\'] ?? null)->toBe('src/')
        ->and($core['autoload-dev']['psr-4']['CoreX\\Testing\\'] ?? null)->toBe('tests/Support/')
        ->and($core['autoload-dev']['_comment'] ?? null)->toBeNull()
        ->and($monorepo['autoload-dev']['psr-4']['CoreX\\Testing\\'] ?? null)->toBe('packages/core/tests/Support/')
        ->and(is_dir($root.'/packages/core/src/Testing'))->toBeFalse()
        ->and(is_file($root.'/packages/core/tests/Support/CoreXTestCase.php'))->toBeTrue()
        ->and(is_file($root.'/packages/core/tests/Support/IsolatesDatabasePath.php'))->toBeTrue();

    expect(testingProductionAutoloadOffenders($root))->toBe([])
        ->and(testingHarnessSrcOffenders($root))->toBe([]);
});

test('package tests do not import another package Testing namespace', function (): void {
    expect(testingCrossPackageImportOffenders(SourceScanner::repoRoot()))->toBe([]);
});

test('R-T1 planting in ActorType names the file', function (): void {
    $line = rt1Plant(
        'packages/core/src/Audit/Enums/ActorType.php',
        CoreXTestCase::class,
    );

    expect($line)->toContain('R-T1')
        ->and($line)->toContain('ActorType.php')
        ->and($line)->toContain('CoreXTestCase');
});

/**
 * @return list<string>
 */
function testingHarnessSrcOffenders(string $root): array
{
    $offenders = [];
    $packages = $root.'/packages';

    foreach (new DirectoryIterator($packages) as $package) {
        if (! $package->isDir() || $package->isDot()) {
            continue;
        }

        $src = $package->getPathname().'/src';

        if (! is_dir($src)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);

            foreach (rt1TestingImports($file->getPathname()) as $import) {
                $offenders[] = 'R-T1 '.$relative.' imports '.$import;
            }
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * Production psr-4 must not point at a Testing directory or a *\Testing\ prefix.
 *
 * @return list<string>
 */
function testingProductionAutoloadOffenders(string $root): array
{
    $offenders = [];

    foreach (glob($root.'/packages/*/composer.json') ?: [] as $composerFile) {
        $composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        $relative = substr($composerFile, strlen($root) + 1);

        foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $paths) {
            if (str_contains($prefix, '\\Testing\\')) {
                $offenders[] = $relative.' autoload prefix '.$prefix;
            }

            foreach ((array) $paths as $path) {
                if (str_contains((string) $path, 'Testing')) {
                    $offenders[] = $relative.' autoload path '.$path;
                }
            }
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * A package test may use CoreX\Testing only from core tests or the monorepo root.
 *
 * @return list<string>
 */
function testingCrossPackageImportOffenders(string $root): array
{
    $offenders = [];
    $roots = [
        $root.'/packages',
        $root.'/tests',
    ];

    foreach ($roots as $scan) {
        if (! is_dir($scan)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (! str_contains($path, '/tests/')) {
                continue;
            }

            $relative = substr($path, strlen($root) + 1);
            $imports = rt1TestingImports($path);

            if ($imports === []) {
                continue;
            }

            $imports = array_values(array_filter($imports, static fn (string $import): bool => ! str_starts_with($import, 'CoreX\\Testing\\Stubs\\')));

            $allowed = str_starts_with($relative, 'packages/core/tests/')
                || str_starts_with($relative, 'tests/');

            if ($allowed) {
                continue;
            }

            foreach ($imports as $import) {
                $offenders[] = $relative.' imports '.$import;
            }
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * @return list<string>
 */
function rt1TestingImports(string $absolute): array
{
    $contents = (string) file_get_contents($absolute);
    preg_match_all(
        '/^\s*(?:use|namespace)\s+(?:function\s+|const\s+)?((?:CoreX\\\\Testing|CoreX\\\\Auth\\\\Testing|CoreX\\\\Tasks\\\\Testing|Flex\\\\Crm\\\\Testing)\\\\[^;{]+)/m',
        $contents,
        $matches,
    );

    $imports = [];

    foreach ($matches[1] as $import) {
        $imports[] = trim(explode(' as ', $import, 2)[0]);
    }

    return $imports;
}

function rt1Plant(string $relative, string $import): string
{
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $original = (string) file_get_contents($absolute);
    $needle = "declare(strict_types=1);\n";
    $count = 0;
    $planted = str_replace($needle, $needle."\nuse {$import};\n", $original, $count);

    if ($count !== 1) {
        throw new RuntimeException('R-T1 planting could not insert a use into '.$relative);
    }

    file_put_contents($absolute, $planted);

    try {
        $symbol = substr($import, strrpos($import, '\\') + 1);
        $hits = array_values(array_filter(
            testingHarnessSrcOffenders(SourceScanner::repoRoot()),
            static fn (string $line): bool => str_contains($line, basename($relative))
                && str_contains($line, $symbol),
        ));

        expect($hits)->not->toBe([]);

        return $hits[0];
    } finally {
        file_put_contents($absolute, $original);
    }
}
