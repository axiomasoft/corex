<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests;

use CoreX\Tenancy\TenancyServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Stancl\Tenancy\TenancyServiceProvider as StanclTenancyServiceProvider;

/**
 * root_* is PG-only DDL on the central connection (D14/D16) — no sqlite
 * fallback, mirrors packages/auth's self-contained testbench pattern (the
 * root monorepo tests/ suite does not run this package, D19 exception
 * already established for corex/auth).
 */
abstract class TestCase extends Orchestra
{
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
    }
}
