<?php

declare(strict_types=1);

// P12.3 / P12A.4 — executable package graph gate (R1–R3, D41).

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
        ->and($joined)->toContain('LEGACY NAMESPACE SPLIT: CoreX\\Tenancy\\')
        ->and($joined)->toContain('HOST-RESERVED NAMESPACE: CoreX\\Search\\Meilisearch\\')
        ->and($joined)->toContain('UNDECLARED DEPENDENCY EXCEPTION: corex/tasks-workflow -> corex/wakeup');

    $reportPath = $repoRoot.'/artifacts/P12-package-graph/report.json';
    expect(is_file($reportPath))->toBeTrue();

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);

    expect($report['violations'] ?? null)->toBe([])
        ->and($report['legacy_namespace_splits'] ?? null)->not->toBeEmpty()
        ->and($report['reservations'] ?? null)->not->toBeEmpty()
        ->and($report['undeclared_dependency_exceptions'] ?? null)->not->toBeEmpty();
});

test('package graph gate reports R1 when guard content is removed from guard file', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $guardPath = $repoRoot.'/packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php';
    $originalContents = (string) file_get_contents($guardPath);
    $tamperedContents = str_replace('bound(WakeupScheduler::class)', 'bound(MissingWakeupScheduler::class)', $originalContents);

    file_put_contents($guardPath, $tamperedContents);

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    file_put_contents($guardPath, $originalContents);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(1)
        ->and($joined)->toContain('[R1]')
        ->and($joined)->toContain('SqlTaskRecurrenceMaterializer.php');
});

test('package graph gate reports R3 when host defines classes under reserved prefix', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/search/src/Internal/ProbeMeilisearchViolation.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Search\Meilisearch;

final class ProbeMeilisearchViolation
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    unlink($probePath);
    @unlink($repoRoot.'/artifacts/P12-package-graph/report.json');

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(1)
        ->and($joined)->toContain('[R3]')
        ->and($joined)->toContain('host defines classes under reserved prefix');
});

test('package graph gate reports R1 for wakeup import outside Integration/Wakeup', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $fixture = $repoRoot.'/tests/fixtures/package-graph/tenancy-wakeup-foreign-context.php';
    $probePath = $repoRoot.'/packages/tenancy/src/Account/WakeupForeignContextProbe.php';

    expect(is_file($fixture))->toBeTrue();
    copy($fixture, $probePath);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1)
            ->and($joined)->toContain('[R1]')
            ->and($joined)->toContain('WakeupForeignContextProbe.php')
            ->and($joined)->toContain('corex/wakeup');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});
