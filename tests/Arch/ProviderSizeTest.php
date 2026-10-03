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
        ->and($snapshot['max_bindings'])->toBe(8)
        ->and($snapshot)->not->toHaveKey('note')
        ->and($snapshot['forbid_anonymous_classes'])->toBeTrue()
        ->and($snapshot['single_return_factories'])->toBeTrue();
});

test('provider size gate exits 1 and names the planted provider', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/ProviderSizeProbeProvider.php';
    $calls = [];

    for ($index = 0; $index < 9; $index++) {
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
            ->and($joined)->toContain('S1 packages/core/src/Config/ProviderSizeProbeProvider.php')
            ->and($joined)->toContain('singleton=9')
            ->and($joined)->toContain('total=9')
            ->and($joined)->toContain('max_bindings=8');
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

/**
 * @param  string  $relative  probe path below the repository root
 */
function providerSizeProbeRun(string $relative, string $contents): array
{
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/'.$relative;

    file_put_contents($probePath, $contents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/provider-size-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }

    return [$exitCode, implode("\n", $output)];
}

test('provider size gate rejects an anonymous class in a provider (S2)', function (): void {
    [$exitCode, $joined] = providerSizeProbeRun('packages/core/src/Config/AnonymousClassProbeProvider.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

use Illuminate\Support\ServiceProvider;

final class AnonymousClassProbeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('probe', static fn (): object => new class {});
    }
}
PHP);

    expect($exitCode)->toBe(1, $joined)
        ->and($joined)->toContain('S2 packages/core/src/Config/AnonymousClassProbeProvider.php:');
});

test('provider size gate rejects a multi-statement factory closure in a provider (S3)', function (): void {
    [$exitCode, $joined] = providerSizeProbeRun('packages/core/src/Config/ClosureProbeProvider.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

use Illuminate\Support\ServiceProvider;

final class ClosureProbeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('probe', static function (): object {
            $value = new \stdClass;

            return $value;
        });
    }
}
PHP);

    expect($exitCode)->toBe(1, $joined)
        ->and($joined)->toContain('S3 packages/core/src/Config/ClosureProbeProvider.php:');
});

test('provider size gate rejects a multi-statement factory closure in a registrar (S3)', function (): void {
    [$exitCode, $joined] = providerSizeProbeRun('packages/core/src/Config/Laravel/ClosureProbeRegistrar.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

final class ClosureProbeRegistrar
{
    public function register(object $app): void
    {
        $app->singleton('probe', static function (): object {
            $value = new \stdClass;

            return $value;
        });
    }
}
PHP);

    expect($exitCode)->toBe(1, $joined)
        ->and($joined)->toContain('S3 packages/core/src/Config/Laravel/ClosureProbeRegistrar.php:');
});

test('provider size gate accepts single-return closures and arrow functions', function (): void {
    [$exitCode, $joined] = providerSizeProbeRun('packages/core/src/Config/Laravel/SingleReturnProbeRegistrar.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

final class SingleReturnProbeRegistrar
{
    public function register(object $app): void
    {
        $app->singleton('one', static function (): object {
            return new \stdClass;
        });
        $app->singleton('two', static fn (): object => new \stdClass);
    }
}
PHP);

    expect($exitCode)->toBe(0, $joined)
        ->and($joined)->toContain('provider-size-gate: OK');
});
