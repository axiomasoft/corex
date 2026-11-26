<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests;

use CoreX\CoreServiceProvider;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use CoreX\Tenancy\TenancyServiceProvider;
use CoreX\Tenancy\Tests\Stubs\StubIdentityProvider;
use CoreX\Tenancy\Tests\Support\IsolatesDatabasePath;
use CoreX\Tenancy\Tests\Support\TestAccountResources;
use CoreX\Tenancy\Tests\Support\TestTemplateIntegrity;
use CoreX\Tenancy\Tests\Support\TestTenantHost;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\Schema\Testing\MigratesSchemaSets;
use Tenant\Contracts\Context\ContextResolver;
use Tenant\Contracts\Verification\BindingVerifier;
use Tenant\Laravel\TenantServiceProvider;

/**
 * root_* is PG-only DDL on the central connection — no sqlite
 * fallback, mirrors packages/access's self-contained testbench pattern (the
 * root monorepo tests/ suite does not run this package, exception
 * already established for axiomasoft/corex-access).
 */
abstract class TestCase extends Orchestra
{
    use IsolatesDatabasePath {
        resolveApplicationCore as private resolveIsolatedApplicationCore;
    }
    use MigratesSchemaSets {
        migrateSchemaSets as public;
    }

    /**
     * OidcLoginTest/GlobalLogoutTest opt-in flag — file-scoped via a
     * plain `beforeAll()`/`afterAll()` pair in THOSE TWO FILES ONLY (never
     * `uses()->beforeAll()`, which is directory-wide and would leak into
     * every other Feature test). Reset to `false` in `afterAll()` so a
     * random suite-execution order never lets one file's flip bleed into
     * another file's tests — `resolveApplicationCore()` below re-reads it
     * fresh on every single test's own `createApplication()`.
     *
     * A second TestCase subclass (`OidcTestCase`) was the first attempt —
     * abandoned: Pest hard-forbids two `uses()` calls applying two
     * different base classes to the same file/directory
     * (`TestCaseAlreadyInUse` — `vendor/pestphp/pest/src/Repositories/
     * TestRepository.php::make()`), and `phpunit.xml`'s `<testsuite>` here
     * only scans `tests/Feature` — a sibling `tests/Oidc` directory would
     * never run under `composer test`.
     */
    public static bool $oidcEnabled = false;

    /** Физический host-profile включаем только в регрессии порядка middleware. */
    public static bool $hostIdentificationEnabled = false;

    public static bool $runtimeSessionEnabled = false;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            // Core's scoped semantic restorer is decorated by tenancy.
            // Testbench disables package discovery, so register both owners.
            // The host supplies Tenant runtime facilities and trusted account projections.
            TenantServiceProvider::class,
            TenancyServiceProvider::class,
        ];
    }

    /**
     * `TenancyServiceProvider::register()` reads `config('corex-tenancy.oidc.
     * enabled')` — populated from `config/corex-tenancy.php`'s
     * `env('COREX_TENANCY_OIDC_ENABLED', false)` at `mergeConfigFrom()` time —
     * BEFORE Testbench's `defineEnvironment()` hook ever runs (Testbench's
     * `resolveApplicationBootstrappers()` calls `RegisterProviders::
     * bootstrap()`, i.e. every provider's `register()`, BEFORE calling
     * `defineEnvironment()`: `vendor/orchestra/testbench-core/src/Concerns/
     * CreatesApplication.php` lines 534 vs 543). `config()->set()` in
     * `defineEnvironment()` is therefore too late for this ONE
     * register()-time conditional binding (though early enough for the
     * boot()-time route registration, which runs later still). Only a real
     * process env var, set here — `resolveApplicationCore()` runs earliest
     * of all the overridable hooks, before config/provider bootstrapping —
     * reaches `register()` in time. Runs fresh on every test's own
     * `createApplication()`, so it cannot leak between files regardless of
     * PHPUnit's random suite order.
     */
    #[Override]
    protected function resolveApplicationCore($app): void
    {
        putenv('COREX_TENANCY_OIDC_ENABLED='.(static::$oidcEnabled ? 'true' : 'false'));

        $this->resolveIsolatedApplicationCore($app);
    }

    /** Per-test filesystem fixtures for the host cache and CoreX session middleware. */
    private ?string $runtimeFixtures = null;

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('q', 32)));
        $this->runtimeFixtures = sys_get_temp_dir().'/corex-tenancy-runtime-'.bin2hex(random_bytes(8));
        (new Filesystem)->makeDirectory($this->runtimeFixtures.'/cache', 0700, true);
        (new Filesystem)->makeDirectory($this->runtimeFixtures.'/sessions', 0700, true);
        $app['config']->set('cache.stores.tenant_file', [
            'driver' => 'file',
            'path' => $this->runtimeFixtures.'/cache',
            'lock_path' => $this->runtimeFixtures.'/cache',
        ]);
        $app['config']->set('session.driver', 'file');
        $app['config']->set('session.files', $this->runtimeFixtures.'/sessions');
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            // Match the UTC timestamps used by the test clock on every server.
            'timezone' => 'UTC',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
        $app['config']->set('corex-tenancy.host_identification.enabled', static::$hostIdentificationEnabled);
        $app['config']->set('tenant', [
            'runtime_profile' => 'laravel-pg-database', 'central_connection' => 'pgsql',
            'cache_stores' => [], 'central_cache_stores' => [],
            'facilities' => ['database' => true, 'cache' => false, 'session' => static::$runtimeSessionEnabled, 'storage' => false],
            'storage_driver' => 'local',
            'shared' => [
                'cache' => ['owner' => 'test:host', 'purpose' => 'test:domain'],
                'session' => ['owner' => 'test:host', 'purpose' => 'test:domain'],
                'storage' => ['owner' => 'test:host', 'purpose' => 'test:domain'],
            ],
        ]);
        $host = new TestTenantHost($app->make('db'), $this->runtimeFixtures);
        $app->instance(TestTenantHost::class, $host);
        $app->instance(AccountIsolationMapper::class, $host);
        $app->instance(ContextResolver::class, $host);
        $app->instance(BindingVerifier::class, $host);
        $app->singleton(TestAccountResources::class);
        $app->alias(TestAccountResources::class, AccountResources::class);
        $app->singleton(TestTemplateIntegrity::class);
        $app->alias(TestTemplateIntegrity::class, TemplateIntegrityVerifier::class);
        $app->alias(TestTemplateIntegrity::class, TemplateIntegrityChecks::class);

        if (! static::$oidcEnabled) {
            return;
        }

        // Everything below is read LAZILY (closures resolved on first use,
        // long after this method returns) — safe to set via config()->set()
        // here despite the register()-time gate above being too late for
        // the 'enabled' flag itself.
        $app['config']->set('corex-tenancy.oidc.issuer', 'https://idp.corex.test');
        $app['config']->set('corex-tenancy.oidc.client_id', 'oidc-test-client');
        $app['config']->set('corex-tenancy.oidc.client_secret', 'oidc-test-client-secret');
        $app['config']->set('corex-tenancy.oidc.redirect_uri', 'https://app.example.test/auth/callback');

        // Stands in for the app-configured `auth.provider` a real consumer
        // would wire to axiomasoft/corex-access's `corex-principal` driver —
        // axiomasoft/corex-tenancy itself does not depend on axiomasoft/corex-access. The
        // seam `OidcLoginController`/`LogoutController` resolve through via
        // `Auth::guard()->loginUsingId()`/`->id()` (parity, premortem C4).
        $app->singleton(StubIdentityProvider::class);
        $app['config']->set('auth.providers.users.driver', 'oidc-test-stub');

        $app->resolving('auth', static function ($auth) use ($app): void {
            $auth->provider('oidc-test-stub', static fn () => $app->make(StubIdentityProvider::class));
        });
    }

    /**
     * Feature fixtures deliberately rebuild the central `public` schema to
     * exercise root DDL. The root PG suite is randomized and also discovers
     * these package tests, so leave the database with a schema that the next
     * independently booted Testbench application can connect to.
     */
    #[Override]
    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement('CREATE SCHEMA IF NOT EXISTS public');
        } finally {
            try {
                // Testbench applications can remain reachable until cyclic GC runs.
                // Release every owned PDO now, including the schema-restoration connection.
                if ($this->app !== null && $this->app->bound('db')) {
                    foreach ($this->app->make('db')->getConnections() as $connection) {
                        $connection->disconnect();
                    }
                }
            } finally {
                try {
                    parent::tearDown();
                } finally {
                    if ($this->runtimeFixtures !== null) {
                        (new Filesystem)->deleteDirectory($this->runtimeFixtures);
                        $this->runtimeFixtures = null;
                    }
                }
            }
        }
    }

    public static function trustTemplate(string $templateId, string $database): void
    {
        resolve(TestTemplateIntegrity::class)->trust($templateId, $database);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Domain fixtures address central PostgreSQL outside an active Tenant frame.
        DB::setDefaultConnection('pgsql');
    }
}
