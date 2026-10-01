<?php

declare(strict_types=1);

test('package docs gate reports every cohort package and assigns producer phases', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-docs-gate.php').' --json 2>/dev/null';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $report = json_decode(implode("\n", $output), true);

    expect($report)->toBeArray()
        ->and($report['schema_version'])->toBe('corex-package-docs-gate/1')
        ->and($report['cohort_count'])->toBe(20)
        ->and($report['packages'])->toHaveCount(20);

    foreach ($report['violations'] as $violation) {
        expect($violation['package'])->not->toBe('')
            ->and($violation['path'])->toContain('README.md')
            ->and($violation['producer_phase'])->toMatch('/^P\d+$/')
            ->and($violation['heading'])->not->toBe('');
    }
});

test('package docs gate fails a planted missing heading', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $readme = $repoRoot.'/packages/search-intl/README.md';
    $original = (string) file_get_contents($readme);
    $planted = preg_replace('/^## Installation\R/m', "## Unrelated\n", $original, 1);
    $planted = str_replace("composer require axiomasoft/corex-search-intl\n", '', (string) $planted);

    file_put_contents($readme, $planted);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-docs-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('packages/search-intl/README.md')
            ->and($joined)->toContain('heading=installation')
            ->and($joined)->toContain('producer=P12');
    } finally {
        file_put_contents($readme, $original);
    }
});

test('package docs gate fails a planted Internal example', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $readme = $repoRoot.'/packages/recurrence/README.md';
    $original = (string) file_get_contents($readme);
    $plant = $original."\n```php\nuse CoreX\\Recurrence\\Internal\\HiddenExpander;\nnew HiddenExpander();\n```\n";

    file_put_contents($readme, $plant);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-docs-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('packages/recurrence/README.md')
            ->and($joined)->toContain('CoreX\\Recurrence\\Internal\\HiddenExpander')
            ->and($joined)->toContain('Internal')
            ->and($joined)->toContain('producer=P4');
    } finally {
        file_put_contents($readme, $original);
    }
});
