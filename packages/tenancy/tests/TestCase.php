<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests;

use CoreX\CoreServiceProvider;
use CoreX\Database\Testing\MigratesSchemaSets;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier as TemplateIntegrityVerifierContract;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Internal\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\TenancyServiceProvider;
use CoreX\Tenancy\Tests\Stubs\StubIdentityProvider;
use CoreX\Tenancy\Tests\Support\IsolatesDatabasePath;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

/**
 * root_* is PG-only DDL on the central connection (D14/D16) — no sqlite
 * fallback, mirrors packages/access's self-contained testbench pattern (the
 * root monorepo tests/ suite does not run this package, D19 exception
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
     * P2.22 (OidcLoginTest/GlobalLogoutTest) opt-in flag — file-scoped via a
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

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            // Core's scoped semantic restorer is decorated by tenancy (D49).
            // Testbench disables package discovery, so register both owners.
            // The package owns its physical tenancy engine (PLAN3.D95).
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

    /** Per-test cache/session fixtures; the tenant isolation under test needs real stores, never `array`. */
    private ?string $runtimeFixtures = null;

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $this->runtimeFixtures = sys_get_temp_dir().'/corex-tenancy-runtime-'.bin2hex(random_bytes(8));
        (new Filesystem)->makeDirectory($this->runtimeFixtures.'/cache', 0700, true);
        (new Filesystem)->makeDirectory($this->runtimeFixtures.'/sessions', 0700, true);
        $app['config']->set('cache.stores.tenant_file', [
            'driver' => 'file',
            'path' => $this->runtimeFixtures.'/cache',
            'lock_path' => $this->runtimeFixtures.'/cache',
        ]);
        $app['config']->set('corex-tenancy.cache_stores', ['tenant_file']);
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
        // would wire to axiomasoft/corex-access's `corex-principal` driver (D125) —
        // axiomasoft/corex-tenancy itself does not depend on axiomasoft/corex-access (AC-28). The
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
        $central = DB::connection('pgsql');
        $configuration = $central->getConfig();
        $configuration['database'] = $database;
        $templateConnection = (new ConnectionFactory(app()))->make($configuration, 'corex-template-test-catalogue');
        $serializer = app(SchemaCatalogueSerializer::class);

        try {
            $schemaHash = $serializer->hash($templateConnection);
        } finally {
            $templateConnection->disconnect();
        }

        $template = DB::table('root_templates')->where('id', $templateId)->first(['id', 'version', 'embedding_model', 'embedding_dim']);
        $payload = [
            'canonical_schema_sha256' => $schemaHash,
            'embedding' => [
                'dimension' => (string) $template->embedding_dim,
                'model' => $template->embedding_model,
            ],
            'format_version' => 1,
            'template_id' => $template->id,
            'template_version' => (string) $template->version,
        ];
        $keypair = sodium_crypto_sign_keypair();
        $signature = sodium_crypto_sign_detached(
            'corex-template-manifest/v1'."\0".$serializer->encode($payload),
            sodium_crypto_sign_secretkey($keypair),
        );
        $base64Url = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        DB::table('root_templates')->where('id', $templateId)->update(['schema_hash' => $schemaHash]);
        config([
            'corex-tenancy.template_integrity.trusted_keys' => ['test' => $base64Url(sodium_crypto_sign_publickey($keypair))],
            'corex-tenancy.template_integrity.manifests' => [
                $templateId => [
                    'payload' => $payload,
                    'signature' => $base64Url($signature),
                    'trusted_key_id' => 'test',
                ],
            ],
        ]);
        app()->forgetInstance(TenancyOptions::class);
        // Singletons that captured the previous options snapshot must be rebuilt against the new one.
        app()->forgetInstance(TemplateIntegrityVerifier::class);
        app()->forgetInstance(TemplateIntegrityVerifierContract::class);
    }
}
