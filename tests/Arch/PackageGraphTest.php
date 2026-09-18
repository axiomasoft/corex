<?php

declare(strict_types=1);

// P12.3 — executable package graph gate (R1–R3, D41).

test('package graph gate reports no violations', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0)
        ->and($output)->not->toBeEmpty();

    $joined = implode("\n", $output);

    expect($joined)->toContain('package-graph-gate: OK')
        ->and($joined)->toContain('LEGACY NAMESPACE SPLIT: CoreX\\Tenancy\\');

    $reportPath = $repoRoot.'/artifacts/P12-package-graph/report.json';
    expect(is_file($reportPath))->toBeTrue();

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);

    expect($report['violations'] ?? null)->toBe([])
        ->and($report['legacy_namespace_splits'] ?? null)->not->toBeEmpty();
});
