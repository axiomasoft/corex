<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\TenancyServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

/**
 * The runtime is the package's own engine (PLAN3.D95/D98 п.6): the fork's service provider must not
 * be discovered, because it would re-register vendor listeners, middleware groups and bootstrappers
 * next to ours. The package manifest of the monorepo root has to skip it explicitly.
 */
it('does not discover the stancl provider in the monorepo root package manifest', function (): void {
    $root = dirname(__DIR__, 4);

    if (! is_file($root.'/vendor/composer/installed.json')) {
        $this->markTestSkipped('The monorepo root vendor directory is not installed.');
    }

    $manifestPath = sys_get_temp_dir().'/corex-package-manifest-'.bin2hex(random_bytes(6)).'.php';

    try {
        $providers = (new PackageManifest(new Filesystem, $root, $manifestPath))->providers();
    } finally {
        @unlink($manifestPath);
    }

    expect($providers)->toContain(TenancyServiceProvider::class)
        ->and(array_filter($providers, static fn (string $provider): bool => str_starts_with($provider, 'Stancl\\')))->toBe([])
        ->and(json_decode((string) file_get_contents($root.'/composer.json'), true)['extra']['laravel']['dont-discover'])
        ->toContain('axiomasoft/stancl-tenancy');
});

it('boots without any stancl provider and still generates account ids and resolves the tenancy engine', function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->artisan('migrate', ['--database' => 'pgsql', '--path' => realpath(__DIR__.'/../../database/migrations/root'), '--realpath' => true, '--force' => true])->run();

    expect(array_filter(array_keys(app()->getLoadedProviders()), static fn (string $provider): bool => str_starts_with($provider, 'Stancl\\')))->toBe([])
        ->and(app(TenancyManager::class))->toBeInstanceOf(DatabaseTenancyManager::class);

    $cluster = (string) Str::uuid();
    $identity = (string) Str::uuid();
    DB::table('root_clusters')->insert(['id' => $cluster, 'name' => 'c', 'pg_host_writer' => 'w.test', 'pgbouncer_host' => 'p.test', 'app_db_user' => 'corex']);
    DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
    $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => 'disc_'.bin2hex(random_bytes(4)).'_test', 'slug' => 'acc-disc', 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);

    expect(Str::isUuid($account->id))->toBeTrue();
});
