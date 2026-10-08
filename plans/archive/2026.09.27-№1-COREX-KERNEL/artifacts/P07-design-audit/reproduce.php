<?php

declare(strict_types=1);

// Read-only design counterexamples. No application bootstrap or database connection.

namespace CoreX\Tenancy\Runtime\Contracts {
    use CoreX\Context\Data\DatabaseConnectionConfig;
    use CoreX\Context\Data\TenantContext;

    interface TenantBootstrapper
    {
        public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void;

        public function revert(): void;
    }
}

namespace CoreX\Tenancy\Runtime\Events {
    final readonly class TenantContextInitialized
    {
        public function __construct(public string $accountId) {}
    }
    final readonly class TenantContextEnded
    {
        public function __construct(public string $accountId) {}
    }
}

namespace CoreX\Tenancy\Runtime\Exceptions {
    final class TenantContextUnavailable extends \RuntimeException
    {
        public static function missingAccount(string $id): self
        {
            return new self('missing-account:'.$id);
        }
    }
}

namespace {
    require dirname(__DIR__, 4).'/vendor/autoload.php';

    use CoreX\Context\Contracts\AccountConnectionResolver;
    use CoreX\Context\Contracts\TenancyManager;
    use CoreX\Context\Data\AccountRef;
    use CoreX\Context\Data\DatabaseConnectionConfig;
    use CoreX\Context\Data\TenantContext;
    use CoreX\Tenancy\Account\Contracts\AccountRead;
    use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
    use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
    use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManagerMutant;
    use Illuminate\Cache\CacheManager;
    use Illuminate\Config\Repository;
    use Illuminate\Container\Container;
    use Illuminate\Events\Dispatcher;
    use Illuminate\Filesystem\Filesystem;
    use Illuminate\Foundation\PackageManifest;

    $examples = file_get_contents(dirname(__DIR__).'/P07-design/examples.md');
    preg_match('/## 1\. `DatabaseTenancyManager`.*?```php\n(.*?)\n```/s', $examples, $match);

    if (! isset($match[1])) {
        throw new RuntimeException('Manager example not found');
    }
    // Execute exactly the current normative example, never a corrected copy.
    eval(preg_replace('/^<\?php\s*/', '', $match[1], 1));

    final class Accounts implements AccountRead
    {
        public function find(string $accountId, ?string $connectionName = null): ?AccountRef
        {
            return $accountId === 'missing' ? null : account($accountId);
        }

        public function ownerIdentityId(string $accountId, ?string $connectionName = null): ?string
        {
            return null;
        }
    }
    final class Connections implements AccountConnectionResolver
    {
        public function resolve(AccountRef $account): DatabaseConnectionConfig
        {
            return new DatabaseConnectionConfig('pgsql', 'unused', 5432, $account->id, 'unused');
        }
    }
    final class Facility implements TenantBootstrapper
    {
        public string $state = 'central';

        public ?string $failFor = null;

        public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void
        {
            $this->state = $context->account->id;

            if ($this->failFor === $this->state) {
                throw new RuntimeException('bootstrap-failed');
            }
        }

        public function revert(): void
        {
            $this->state = 'central';
        }
    }
    function account(string $id): AccountRef
    {
        return new AccountRef($id, $id, 'active', [], []);
    }
    function manager(Container $container, array $bootstrappers): DatabaseTenancyManager
    {
        return new DatabaseTenancyManager($bootstrappers, new Accounts, new Connections, $container, new Dispatcher($container), 'central');
    }
    $results = ['example_sha256' => hash('sha256', $match[1]), 'php' => PHP_VERSION];

    $facility = new Facility;
    $m = manager(new Container, [$facility]);
    $m->initialize(account('A'));

    try {
        $m->runFor(account('missing'), null, static fn () => null);
    } catch (Throwable $error) {
    }
    $results['runFor_missing_account'] = ['expected_account' => 'A', 'actual_account' => $m->context()?->account->id, 'facility' => $facility->state];

    $facility = new Facility;
    $facility->failFor = 'B';
    $m = manager(new Container, [$facility]);

    try {
        $m->initialize(account('B'));
    } catch (Throwable $error) {
    }
    $m->end();
    $results['partially_failed_bootstrap'] = ['expected_facility' => 'central', 'actual_facility' => $facility->state, 'initialized' => $m->initialized()];

    // Apply the declared processing()/finished() algorithm around a synchronous job.
    $m = manager(new Container, [new Facility]);
    $m->initialize(account('A'));
    $payload = ['account' => 'A', 'workspace' => null];
    $m->end();
    $m->initialize(account($payload['account']));
    $inside = $m->context()?->account->id;
    $m->end();
    $results['sync_queue_outer_context'] = ['inside' => $inside, 'expected_after_dispatch' => 'A', 'actual_after_dispatch' => $m->context()?->account->id];

    $container = new Container;
    $m = manager($container, [new Facility]);
    $container->scoped('writer', static fn () => new stdClass);
    $m->initialize(account('A'));
    $before = $container->make('writer');
    $m->runFor(account('A'), null, static fn () => null);
    $results['same_account_scope_reset'] = ['expected_same_instance' => false, 'actual_same_instance' => $before === $container->make('writer')];

    $mutant = str_replace('final class DatabaseTenancyManager ', 'final class DatabaseTenancyManagerMutant ', $match[1]);
    $mutant = str_replace("        \$this->container->forgetScopedInstances();\n        \$this->events->dispatch(new TenantContextInitialized", '        $this->events->dispatch(new TenantContextInitialized', $mutant, $removed);

    if ($removed !== 1) {
        throw new RuntimeException('Expected exactly one initialize reset');
    }
    eval(preg_replace('/^<\?php\s*/', '', $mutant, 1));
    $samples = [];
    foreach ([DatabaseTenancyManager::class, DatabaseTenancyManagerMutant::class] as $class) {
        $container = new Container;
        $m = new $class([new Facility], new Accounts, new Connections, $container, new Dispatcher($container), 'central');
        $container->scoped('writer', static fn () => (object) ['account' => $m->context()?->account->id]);
        $m->initialize(account('A'));
        $held = $container->make('writer');
        $inside = $m->runFor(account('B'), null, static fn () => $container->make('writer')->account);
        $samples[$class] = ['held' => $held->account, 'inside' => $inside, 'after' => $container->make('writer')->account, 'after_is_held' => $container->make('writer') === $held];
    }
    $results['declared_scope_mutant_survives'] = ['samples' => $samples, 'identical' => count(array_unique(array_map('json_encode', $samples))) === 1];

    // A bounded guard reports the recursive construction before PHP exhausts memory.
    $container = new Container;
    $trace = [];
    $container->singleton(TenancyManager::class, static function (Container $c) use (&$trace) {
        $trace[] = 'TenancyManager';

        if (count($trace) > 8) {
            throw new RuntimeException('constructor-cycle');
        }

        return manager($c, [$c->make('workspace-bootstrapper')]);
    });
    $container->singleton('workspace-bootstrapper', static function (Container $c) use (&$trace) {
        $trace[] = 'WorkspaceContextBootstrapper';

        return new class($c->make(TenancyManager::class)) implements TenantBootstrapper
        {
            public function __construct(private TenancyManager $manager) {}

            public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void {}

            public function revert(): void
            {
                $this->manager->setWorkspace(null);
            }
        };
    });

    try {
        $container->make(TenancyManager::class);
    } catch (Throwable $error) {
        $results['eager_bootstrapper_cycle'] = ['error' => $error->getMessage(), 'trace' => $trace];
    }

    // CacheManager's file driver ignores prefix, even after forgetDriver().
    $cacheDirectory = sys_get_temp_dir().'/corex-p07-cache-'.bin2hex(random_bytes(8));
    mkdir($cacheDirectory);

    try {
        $app = new Container;
        $config = new Repository(['cache' => ['default' => 'file', 'prefix' => 'central_', 'stores' => ['file' => ['driver' => 'file', 'path' => $cacheDirectory]]]]);
        $app->instance('config', $config);
        $app->instance('files', new Filesystem);
        $cache = new CacheManager($app);
        $config->set('cache.stores.file.prefix', 'tenant_A_central_');
        $cache->forgetDriver('file');
        $cache->store('file')->put('secret', 'A-only', 60);
        $config->set('cache.stores.file.prefix', 'tenant_B_central_');
        $cache->forgetDriver('file');
        $results['file_cache_cross_tenant'] = ['expected_value_for_B' => null, 'actual_value_for_B' => $cache->store('file')->get('secret')];
    } finally {
        (new Filesystem)->deleteDirectory($cacheDirectory);
    }

    $manifestDirectory = sys_get_temp_dir().'/corex-p07-manifest-'.bin2hex(random_bytes(8));
    mkdir($manifestDirectory.'/vendor/composer', 0777, true);

    try {
        $installed = json_decode(file_get_contents(dirname(__DIR__, 4).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
        $stancl = array_values(array_filter($installed['packages'], static fn (array $p): bool => $p['name'] === 'axiomasoft/stancl-tenancy'));
        file_put_contents($manifestDirectory.'/vendor/composer/installed.json', json_encode(['packages' => $stancl], JSON_THROW_ON_ERROR));
        $discovery = [];
        foreach (['stancl/tenancy', 'axiomasoft/stancl-tenancy'] as $ignored) {
            file_put_contents($manifestDirectory.'/composer.json', json_encode(['extra' => ['laravel' => ['dont-discover' => [$ignored]]]], JSON_THROW_ON_ERROR));
            $manifest = new PackageManifest(new Filesystem, $manifestDirectory, $manifestDirectory.'/packages.php');
            $manifest->build();
            $discovery[$ignored] = $manifest->providers();
        }
        $results['provider_discovery'] = $discovery;
    } finally {
        (new Filesystem)->deleteDirectory($manifestDirectory);
    }
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}
