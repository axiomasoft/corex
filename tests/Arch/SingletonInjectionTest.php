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

test('singleton injection allowlist is empty since P05.3', function (): void {
    $allowlist = json_decode((string) file_get_contents(SingletonInjectionGate::allowlistPath()), true, flags: JSON_THROW_ON_ERROR);

    expect($allowlist['entries'])->toBe([]);
});

test('singleton injection gate L2 names a singleton that depends on a scoped type', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $files = [
        $repoRoot.'/packages/core/src/Internal/L2ProbeScopedDependency.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

final class L2ProbeScopedDependency {}
PHP,
        $repoRoot.'/packages/core/src/Internal/L2ProbeSingletonConsumer.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

final class L2ProbeSingletonConsumer
{
    public function __construct(private readonly L2ProbeScopedDependency $dependency) {}
}
PHP,
        $repoRoot.'/packages/core/src/Internal/Laravel/L2ProbeRegistrar.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\L2ProbeScopedDependency;
use CoreX\Internal\L2ProbeSingletonConsumer;
use Illuminate\Contracts\Foundation\Application;

final class L2ProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->scoped(L2ProbeScopedDependency::class);
        $app->singleton(L2ProbeSingletonConsumer::class);
    }
}
PHP,
    ];
    $violations = singletonInjectionProbe($files);
    $joined = implode("\n", $violations);

    expect($violations)->not->toBeEmpty()
        ->and($joined)->toContain('L2 CoreX\Internal\L2ProbeSingletonConsumer::$dependency depends on scoped CoreX\Internal\L2ProbeScopedDependency')
        ->and($joined)->toContain('L2ProbeRegistrar.php');
});

test('singleton injection gate L3 names a singleton that captures a database connection', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $files = [
        $repoRoot.'/packages/core/src/Internal/L3ProbeSingletonConsumer.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Database\Connection;

final class L3ProbeSingletonConsumer
{
    public function __construct(private readonly Connection $connection) {}
}
PHP,
        $repoRoot.'/packages/core/src/Internal/Laravel/L3ProbeRegistrar.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\L3ProbeSingletonConsumer;
use Illuminate\Contracts\Foundation\Application;

final class L3ProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(L3ProbeSingletonConsumer::class);
    }
}
PHP,
    ];
    $violations = singletonInjectionProbe($files);
    $joined = implode("\n", $violations);

    expect($violations)->not->toBeEmpty()
        ->and($joined)->toContain('L3 CoreX\Internal\L3ProbeSingletonConsumer::$connection captures a database connection')
        ->and($joined)->toContain('L3ProbeRegistrar.php');
});

test('singleton injection gate L3 sees a connection hidden in a union constructor type', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $violations = singletonInjectionProbe([
        $repoRoot.'/packages/core/src/Internal/L3UnionProbe.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;

final class L3UnionProbe
{
    public function __construct(private readonly Connection|ConnectionResolverInterface $connection) {}
}
PHP,
        $repoRoot.'/packages/core/src/Internal/Laravel/L3UnionProbeRegistrar.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\L3UnionProbe;
use Illuminate\Contracts\Foundation\Application;

final class L3UnionProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(L3UnionProbe::class);
    }
}
PHP,
    ]);

    expect(implode("\n", $violations))->toContain('L3 CoreX\Internal\L3UnionProbe::$connection captures a database connection');
});

test('singleton injection gate L3 follows a singleton factory that forwards to the container', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $violations = singletonInjectionProbe([
        $repoRoot.'/packages/core/src/Internal/L3HolderProbe.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Database\Connection;

final class L3HolderProbe
{
    public function __construct(private readonly Connection $connection) {}
}
PHP,
        $repoRoot.'/packages/core/src/Internal/Laravel/L3HolderProbeRegistrar.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\L3HolderProbe;
use Illuminate\Contracts\Foundation\Application;

final class L3HolderProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton('l3.holder', static fn (Application $app): L3HolderProbe => $app->make(L3HolderProbe::class));
    }
}
PHP,
    ]);

    expect(implode("\n", $violations))->toContain('L3 CoreX\Internal\L3HolderProbe::$connection captures a database connection');
});

test('singleton injection gate L3 sees a connection inside a DNF constructor type', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $violations = singletonInjectionProbe([
        $repoRoot.'/packages/core/src/Internal/L3DnfProbe.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Countable;
use Illuminate\Database\ConnectionInterface;
use PDO;

final class L3DnfProbe
{
    public function __construct(private readonly (ConnectionInterface&Countable)|PDO $connection) {}
}
PHP,
        $repoRoot.'/packages/core/src/Internal/Laravel/L3DnfProbeRegistrar.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\L3DnfProbe;
use Illuminate\Contracts\Foundation\Application;

final class L3DnfProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(L3DnfProbe::class);
    }
}
PHP,
    ]);

    expect(implode("\n", $violations))->toContain('L3 CoreX\Internal\L3DnfProbe::$connection captures a database connection');
});

test('singleton injection gate L3 follows a typed factory that delegates to a helper', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $violations = singletonInjectionProbe([
        $repoRoot.'/packages/core/src/Internal/L3TypedFactoryProbe.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal;

use Illuminate\Database\Connection;

final class L3TypedFactoryProbe
{
    public function __construct(private readonly Connection $connection) {}
}
PHP,
        $repoRoot.'/packages/core/src/Internal/Laravel/L3TypedFactoryProbeRegistrar.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Internal\Laravel;

use CoreX\Internal\L3TypedFactoryProbe;
use Illuminate\Contracts\Foundation\Application;

final class L3TypedFactoryProbeRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton('l3.typed', static fn (Application $app): L3TypedFactoryProbe => ProbeHelper::build($app));
    }
}
PHP,
    ]);

    expect(implode("\n", $violations))->toContain('L3 CoreX\Internal\L3TypedFactoryProbe::$connection captures a database connection');
});

/**
 * Plants probe sources, runs the gate against the real allowlist and always removes the probes.
 *
 * @param  array<string, string>  $files  absolute path => contents
 * @return list<string>
 */
function singletonInjectionProbe(array $files): array
{
    $directories = [];

    foreach ($files as $path => $contents) {
        $directory = \dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
            $directories[] = $directory;
        }

        file_put_contents($path, $contents);
    }

    try {
        return SingletonInjectionGate::violations(SingletonInjectionGate::allowlistPath());
    } finally {
        foreach (array_keys($files) as $path) {
            @unlink($path);
        }

        foreach ($directories as $directory) {
            @rmdir($directory);
        }
    }
}
