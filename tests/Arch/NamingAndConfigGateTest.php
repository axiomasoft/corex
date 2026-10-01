<?php

declare(strict_types=1);

// P3.6 — naming gate (blocking). Config composition blocking lives in ConfigCompositionTest.

test('naming gate reports no violations on clean tree', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/naming-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(0)
        ->and($joined)->toContain('naming-gate: OK');
});

test('naming gate reports N1 for ProbeHelper in core Contracts', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Settings/Contracts/ProbeHelper.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Settings\Contracts;

#[\CoreX\Attributes\Api]
final class ProbeHelper
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/naming-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    unlink($probePath);

    $joined = implode("\n", $output);

    expect($exitCode)->not->toBe(0)
        ->and($joined)->toContain('ProbeHelper.php')
        ->and($joined)->toContain('N1');
});

test('config composition gate is blocking on a clean tree', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/config-composition-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(0)
        ->and($joined)->toContain('config-composition-gate: OK')
        ->and($joined)->toContain('blocking=true');
});

test('config composition gate names the probe file and exits 1', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/ConfigCompositionProbe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

final class ConfigCompositionProbe
{
    public function probe(): void
    {
        config('probe-key');
    }
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/config-composition-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1)
            ->and($joined)->toContain('ConfigCompositionProbe.php')
            ->and($joined)->toContain('C1');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }
});
