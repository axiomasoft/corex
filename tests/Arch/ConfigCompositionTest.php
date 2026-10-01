<?php

declare(strict_types=1);

// P7.11 — blocking config() composition gate. The plant must name the file.

test('config composition gate exits 0 on a clean tree', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/config-composition-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(0, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('config-composition-gate: OK')
        ->and($joined)->toContain('blocking=true')
        ->and($joined)->toContain('C1 count=0')
        ->and($joined)->not->toMatch('/^C1 /m');
});

test('config composition gate exits 1 and names the planted file', function (): void {
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

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('ConfigCompositionProbe.php')
            ->and($joined)->toContain('C1');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }
});

test('config composition gate catches qualified and dynamic calls without counting methods', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/ConfigCompositionProbe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

final class ConfigCompositionProbe
{
    public function probe(string $key): void
    {
        \config('qualified-key');
        config($key);
        $this->config('method-key');
    }

    private function config(string $key): void {}
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/config-composition-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('ConfigCompositionProbe.php')
            ->and($joined)->toContain('C1 count=2');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }
});
