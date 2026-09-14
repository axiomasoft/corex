<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests;

use CoreX\Tenancy\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\TenancyServiceProvider;
use CoreX\Tenancy\Tests\Stubs\StubIdentityProvider;
use CoreX\Testing\IsolatesDatabasePath;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Stancl\Tenancy\TenancyServiceProvider as StanclTenancyServiceProvider;

/**
 * root_* is PG-only DDL on the central connection (D14/D16) — no sqlite
 * fallback, mirrors packages/auth's self-contained testbench pattern (the
 * root monorepo tests/ suite does not run this package, D19 exception
 * already established for corex/auth).
 */
abstract class TestCase extends Orchestra
{
    use IsolatesDatabasePath {
        resolveApplicationCore as private resolveIsolatedApplicationCore;
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

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // Orchestra\Testbench\TestCase::$enablesPackageDiscoveries defaults
            // to false (composer package auto-discovery, which a real
            // consumer app gets for free, is off in testbench) — stancl's own
            // provider (Tenancy::class singleton, its config/tenancy.php
            // asset defaults, the bootstrapper __constructStatic loop) must
            // be listed explicitly or `tenancy.cache`/`tenancy.database`
            // defaults silently end up as whatever OUR narrower config/
            // tenancy.php happens to project (P2.5).
            StanclTenancyServiceProvider::class,
            TenancyServiceProvider::class,
        ];
    }

    /**
     * `TenancyServiceProvider::register()` reads `config('tenancy.oidc.
     * enabled')` — populated from `config/tenancy.php`'s
     * `env('TENANCY_OIDC_ENABLED', false)` at `mergeConfigFrom()` time —
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
        putenv('TENANCY_OIDC_ENABLED='.(static::$oidcEnabled ? 'true' : 'false'));

        $this->resolveIsolatedApplicationCore($app);
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);

        if (! static::$oidcEnabled) {
            return;
        }

        // Everything below is read LAZILY (closures resolved on first use,
        // long after this method returns) — safe to set via config()->set()
        // here despite the register()-time gate above being too late for
        // the 'enabled' flag itself.
        $app['config']->set('tenancy.oidc.issuer', 'https://idp.corex.test');
        $app['config']->set('tenancy.oidc.client_id', 'oidc-test-client');
        $app['config']->set('tenancy.oidc.client_secret', 'oidc-test-client-secret');
        $app['config']->set('tenancy.oidc.redirect_uri', 'https://app.example.test/auth/callback');

        // Stands in for the app-configured `auth.provider` a real consumer
        // would wire to corex/auth's `corex-principal` driver (D125) —
        // corex/tenancy itself does not depend on corex/auth (AC-28). The
        // seam `OidcLoginController`/`LogoutController` resolve through via
        // `Auth::guard()->loginUsingId()`/`->id()` (parity, premortem C4).
        $app->singleton(StubIdentityProvider::class);
        $app['config']->set('auth.providers.users.driver', 'oidc-test-stub');

        $app->resolving('auth', static function ($auth) use ($app): void {
            $auth->provider('oidc-test-stub', static fn () => $app->make(StubIdentityProvider::class));
        });
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
            'tenancy.template_integrity.trusted_keys' => ['test' => $base64Url(sodium_crypto_sign_publickey($keypair))],
            'tenancy.template_integrity.manifests' => [
                $templateId => [
                    'payload' => $payload,
                    'signature' => $base64Url($signature),
                    'trusted_key_id' => 'test',
                ],
            ],
        ]);
    }
}
