<?php

declare(strict_types=1);

use CoreX\CoreServiceProvider;
use Flex\Modules\ModulesServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

// P1.19 (D33/D43/A19/A62): the sys_*/mod_* migrations are PG-only and never
// auto-loaded — the ONLY prod path is `vendor:publish` + an explicit tenant
// migrate. These tests assert the RESULT of that path (files copied, schema
// built on a clean PG database), not that a command merely did not throw.
uses()->group('pg');

const PUBLISH_TEST_CONNECTION = 'pgsql';

function publishTarget(): string
{
    return database_path('migrations');
}

function resetPublicSchema(): void
{
    DB::connection(PUBLISH_TEST_CONNECTION)->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection(PUBLISH_TEST_CONNECTION)->statement('CREATE SCHEMA public');
    DB::purge(PUBLISH_TEST_CONNECTION);
}

/**
 * Copy just-published migration files into an isolated temp directory so
 * `migrate --path` runs ONLY them on a clean schema (the app's migrations dir
 * also holds the testbench framework migrations).
 *
 * @param  list<string>  $files
 */
function migratePublishedInIsolation(array $files): string
{
    $dir = sys_get_temp_dir().'/corex_publish_'.bin2hex(random_bytes(4));
    mkdir($dir);

    foreach ($files as $file) {
        copy($file, $dir.'/'.basename($file));
    }

    resetPublicSchema();

    test()->artisan('migrate', [
        '--database' => PUBLISH_TEST_CONNECTION,
        '--path' => $dir,
        '--realpath' => true,
        '--force' => true,
    ])->run();

    return $dir;
}

/**
 * @param  list<string>  $paths
 */
function removeFiles(array $paths): void
{
    foreach ($paths as $path) {
        @unlink($path);
    }
}

it('publishes exactly the 7 sys_* migrations and they migrate on a clean PG schema', function (): void {
    $target = publishTarget();

    // Idempotent pre-clean: a prior failed run must not skew the count.
    removeFiles(glob($target.'/*_create_sys_*_table.php') ?: []);

    $this->artisan('vendor:publish', ['--tag' => 'corex-migrations', '--force' => true])->run();

    $published = glob($target.'/*_create_sys_*_table.php') ?: [];
    expect($published)->toHaveCount(7);

    $isolated = migratePublishedInIsolation($published);

    expect(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('sys_settings'))->toBeTrue()
        ->and(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('sys_audit_log'))->toBeTrue()
        ->and(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('sys_feature_flags'))->toBeTrue()
        ->and(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('sys_departments'))->toBeTrue();

    removeFiles(glob($isolated.'/*') ?: []);
    rmdir($isolated);
    removeFiles($published);
});

it('publishes exactly the 3 mod_* migrations and they migrate on a clean PG schema', function (): void {
    // The publish group is registered by ModulesServiceProvider (not part of
    // the default package providers for corex/core tests).
    $this->app->register(ModulesServiceProvider::class);

    $target = publishTarget();
    removeFiles(glob($target.'/*_create_mod_*_table.php') ?: []);

    $this->artisan('vendor:publish', ['--tag' => 'flex-modules-migrations', '--force' => true])->run();

    $published = glob($target.'/*_create_mod_*_table.php') ?: [];
    expect($published)->toHaveCount(3);

    $isolated = migratePublishedInIsolation($published);

    expect(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('mod_modules'))->toBeTrue()
        ->and(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('mod_records'))->toBeTrue()
        ->and(Schema::connection(PUBLISH_TEST_CONNECTION)->hasTable('mod_lifecycle_log'))->toBeTrue();

    removeFiles(glob($isolated.'/*') ?: []);
    rmdir($isolated);
    removeFiles($published);
});

it('exposes the -tenant connection-scope alias for both migration sets (D43)', function (): void {
    $this->app->register(ModulesServiceProvider::class);

    $core = ServiceProvider::pathsToPublish(
        CoreServiceProvider::class,
        'corex-migrations-tenant',
    );
    $modules = ServiceProvider::pathsToPublish(
        ModulesServiceProvider::class,
        'flex-modules-migrations-tenant',
    );

    expect($core)->not->toBeEmpty()
        ->and(array_key_first($core))->toEndWith('database/migrations/pg')
        ->and($modules)->not->toBeEmpty()
        ->and(array_key_first($modules))->toEndWith('database/migrations');
});

it('declares explicit µs precision on every sys_*/mod_* timestamptz column (D32)', function (): void {
    $this->app->register(ModulesServiceProvider::class);

    resetPublicSchema();

    foreach ([
        realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        realpath(__DIR__.'/../../packages/modules/database/migrations'),
    ] as $path) {
        $this->artisan('migrate', [
            '--database' => PUBLISH_TEST_CONNECTION,
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ])->run();
    }

    /** @var list<stdClass> $columns */
    $columns = DB::connection(PUBLISH_TEST_CONNECTION)->select(
        <<<'SQL'
        SELECT table_name, column_name, datetime_precision
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND data_type = 'timestamp with time zone'
          AND (table_name LIKE 'sys\_%' OR table_name LIKE 'mod\_%')
        ORDER BY table_name, column_name
        SQL
    );

    // Sanity: the schema really has timestamptz columns (a broken migrate would
    // make an empty-set assertion vacuously pass).
    expect($columns)->not->toBeEmpty();

    // Every sys_*/mod_* timestamptz must be precision 6 (D32): PG rounds
    // timestamp(0), which would corrupt append-only audit ordering.
    $offenders = array_values(array_map(
        static fn (stdClass $c): string => "{$c->table_name}.{$c->column_name}={$c->datetime_precision}",
        array_filter(
            $columns,
            static fn (stdClass $c): bool => (int) $c->datetime_precision !== 6,
        ),
    ));

    expect($offenders)->toBeEmpty();
});

it('assigns a monotonic bigserial seq cursor to sys_audit_log rows (D42)', function (): void {
    resetPublicSchema();

    $this->artisan('migrate', [
        '--database' => PUBLISH_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // Two append-only rows written in order — seq is the clock-free monotonic
    // cursor keyset pagination sorts by (D42), independent of id/occurred_at.
    foreach (['first', 'second'] as $action) {
        DB::connection(PUBLISH_TEST_CONNECTION)->table('sys_audit_log')->insert([
            'id' => (string) Str::uuid7(),
            'actor_type' => 'system',
            'action' => $action,
        ]);
    }

    /** @var list<int> $seqs */
    $seqs = DB::connection(PUBLISH_TEST_CONNECTION)
        ->table('sys_audit_log')
        ->orderBy('seq')
        ->pluck('seq')
        ->map(static fn (mixed $s): int => (int) $s)
        ->all();

    expect($seqs)->toHaveCount(2)
        ->and($seqs[0])->toBeGreaterThan(0)
        ->and($seqs[1])->toBeGreaterThan($seqs[0]);
});
