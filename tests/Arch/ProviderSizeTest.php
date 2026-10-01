<?php

declare(strict_types=1);

// P8.6 — blocking provider-size gate. The plant must name the file.

test('provider size gate exits 0 on a clean tree', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $snapshot = json_decode((string) file_get_contents($repoRoot.'/scripts/provider-size.json'), true);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/provider-size-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(0, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('provider-size-gate: OK')
        ->and($snapshot['ratchet']['packages/tenancy/src/TenancyServiceProvider.php'])->toBe(0)
        ->and($snapshot['ratchet']['packages/core/src/Modules/ModulesServiceProvider.php'])->toBe(0)
        ->and($snapshot['max_bindings'])->toBe(20);
});

test('provider size gate exits 1 and names the planted provider', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/ProviderSizeProbeProvider.php';
    $calls = [];

    for ($index = 0; $index < 21; $index++) {
        $calls[] = "        \$this->app->singleton('provider.size.probe.{$index}', static fn (): null => null);";
    }

    $body = implode("\n", $calls);
    $probeContents = <<<PHP
<?php

declare(strict_types=1);

namespace CoreX\Config;

use Illuminate\Support\ServiceProvider;

final class ProviderSizeProbeProvider extends ServiceProvider
{
    public function register(): void
    {
{$body}
    }
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/provider-size-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('packages/core/src/Config/ProviderSizeProbeProvider.php')
            ->and($joined)->toContain('singleton=21')
            ->and($joined)->toContain('total=21')
            ->and($joined)->toContain('max_bindings=20');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }
});

test('provider size gate does not count bindings outside a service provider', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/HelperServiceProvider.php';
    $calls = [];

    for ($index = 0; $index < 21; $index++) {
        $calls[] = "        \$app->singleton('provider.size.helper.{$index}', static fn (): null => null);";
    }

    $body = implode("\n", $calls);
    $probeContents = <<<PHP
<?php

declare(strict_types=1);

namespace CoreX\Config;

final class HelperServiceProvider
{
    public function register(object \$app): void
    {
{$body}
    }
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/provider-size-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(0, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('provider-size-gate: OK')
            ->and($joined)->not->toContain('HelperServiceProvider.php');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }
});
