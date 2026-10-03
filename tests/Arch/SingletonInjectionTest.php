<?php

declare(strict_types=1);

use CoreX\Tests\Support\SingletonInjectionGate;

// P4.12 — singleton registrations must not capture Application / Request / Config
// in constructors of monorepo implementations (Octane-safe lifetime; A19).

test('singleton injection gate passes on clean tree with allowlist', function (): void {
    $violations = SingletonInjectionGate::violations(SingletonInjectionGate::allowlistPath());

    expect($violations)->toBe([]);
});

test('singleton injection gate names probe provider for application in constructor', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probeClass = $repoRoot.'/packages/core/src/Internal/SingletonInjectionProbe.php';
    $probeProvider = $repoRoot.'/packages/core/src/Internal/SingletonInjectionProbeServiceProvider.php';

    $probeClassContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Contracts\Foundation\Application;

final class SingletonInjectionProbe
{
    public function __construct(private readonly Application $app) {}
}
PHP;

    $probeProviderContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Support\ServiceProvider;

final class SingletonInjectionProbeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SingletonInjectionProbe::class);
    }
}
PHP;

    if (! is_dir(\dirname($probeClass))) {
        mkdir(\dirname($probeClass), 0755, true);
    }

    file_put_contents($probeClass, $probeClassContents);
    file_put_contents($probeProvider, $probeProviderContents);

    $violations = SingletonInjectionGate::violations(SingletonInjectionGate::allowlistPath());

    unlink($probeClass);
    unlink($probeProvider);

    $joined = implode("\n", $violations);

    expect($violations)->not->toBeEmpty()
        ->and($joined)->toContain('SingletonInjectionProbeServiceProvider.php')
        ->and($joined)->toContain('SingletonInjectionProbe');
});

test('singleton injection gate scans registrars and names a probe registrar that captures the application', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probeClass = $repoRoot.'/packages/core/src/Internal/SingletonInjectionRegistrarProbe.php';
    $probeRegistrar = $repoRoot.'/packages/core/src/Internal/Laravel/SingletonInjectionProbeRegistrar.php';

    $probeClassContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Contracts\Foundation\Application;

final class SingletonInjectionRegistrarProbe
{
    public function __construct(private readonly Application $app) {}
}
PHP;

    $probeRegistrarContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\SingletonInjectionRegistrarProbe;
use Illuminate\Contracts\Foundation\Application;

final class SingletonInjectionProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(SingletonInjectionRegistrarProbe::class);
    }
}
PHP;

    if (! is_dir(\dirname($probeRegistrar))) {
        mkdir(\dirname($probeRegistrar), 0755, true);
    }

    file_put_contents($probeClass, $probeClassContents);
    file_put_contents($probeRegistrar, $probeRegistrarContents);

    try {
        $violations = SingletonInjectionGate::violations(SingletonInjectionGate::allowlistPath());
    } finally {
        unlink($probeClass);
        unlink($probeRegistrar);
        @rmdir(\dirname($probeRegistrar));
    }

    $joined = implode("\n", $violations);

    expect($violations)->not->toBeEmpty()
        ->and($joined)->toContain('SingletonInjectionProbeRegistrar.php')
        ->and($joined)->toContain('SingletonInjectionRegistrarProbe');
});
