<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

// P6.5 — canonical autoload-dev for CoreX\Testing (D33). Production CoreX\ → src/ stays until P16.
// RT1_EXISTING is exactly the three staged src/Testing consumers. A fourth importer is a defect.

/** @var list<string> */
const RT1_EXISTING = [
    'packages/crm/src/Testing/CrmTestCase.php',
    'packages/auth/src/Testing/CoreAuthTestCase.php',
    'packages/tasks/src/Testing/TasksTestCase.php',
];

test('production autoload still covers src and autoload-dev names CoreX\\Testing', function (): void {
    $composer = json_decode(
        (string) file_get_contents(SourceScanner::repoRoot().'/packages/core/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['autoload']['psr-4']['CoreX\\'] ?? null)->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['CoreX\\Testing\\'] ?? null)->toBe('src/Testing/')
        ->and($composer['autoload-dev']['_comment'] ?? null)->toBe(
            'canonical mapping; production psr-4 still covers src/Testing until P16 (D33)',
        )
        ->and(RT1_EXISTING)->toHaveCount(3);
});

test('src files outside the three staged consumers do not import CoreX\\Testing (R-T1)', function (): void {
    expect(rt1Offenders())->toBe([]);

    $root = SourceScanner::repoRoot();

    foreach (RT1_EXISTING as $relative) {
        expect(rt1TestingImports($root.'/'.$relative))->not->toBeEmpty();
    }
});

test('R-T1 planting in ActorType names the file', function (): void {
    $line = rt1Plant(
        'packages/core/src/Enums/ActorType.php',
        'CoreX\\Testing\\CoreXTestCase',
    );

    expect($line)->toContain('R-T1')
        ->and($line)->toContain('ActorType.php')
        ->and($line)->toContain('CoreXTestCase');
});

/**
 * @return list<string>
 */
function rt1Offenders(): array
{
    $offenders = [];
    $root = SourceScanner::repoRoot();
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

            if (in_array($relative, RT1_EXISTING, true)) {
                continue;
            }

            foreach (rt1TestingImports($file->getPathname()) as $import) {
                $offenders[] = 'R-T1 '.$relative.' imports '.$import;
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
        '/^\s*use\s+(?:function\s+|const\s+)?(CoreX\\\\Testing\\\\[^;]+);/m',
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
            rt1Offenders(),
            static fn (string $line): bool => str_contains($line, basename($relative))
                && str_contains($line, $symbol),
        ));

        expect($hits)->not->toBe([]);

        return $hits[0];
    } finally {
        file_put_contents($absolute, $original);
    }
}
