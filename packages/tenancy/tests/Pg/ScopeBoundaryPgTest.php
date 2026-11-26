<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Tests\Support\Scope\MarkerWriter;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tenant\Exceptions\TenantFailure;

uses(TestCase::class)->group('pg');

/**
 * on real databases: the scope boundary is the tenant boundary. Two account databases and
 * the central one each own a `scope_markers` table; a scoped service resolved in a context writes
 * only there, and never into a database of another context.
 */
beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    DB::statement('CREATE TABLE scope_markers (marker text NOT NULL)');

    $this->databases = [];
    $this->accounts = [];

    foreach (['a', 'b'] as $name) {
        $database = 'corex_sb_'.$name.'_'.bin2hex(random_bytes(5)).'_test';
        $this->databases[$name] = $database;
        DB::statement('CREATE DATABASE '.$database);
        $configuration = DB::connection('pgsql')->getConfig();
        $configuration['database'] = $database;
        $connection = new ConnectionFactory(app())->make($configuration, $database);

        try {
            $connection->statement('CREATE TABLE scope_markers (marker text NOT NULL)');
        } finally {
            $connection->disconnect();
        }

        $cluster = (string) Str::uuid();
        $identity = (string) Str::uuid();
        DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $name, 'pg_host_writer' => 'writer.test', 'pgbouncer_host' => 'pool.test', 'app_db_user' => 'corex']);
        DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
        $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => $database, 'slug' => 'acc-'.$name, 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);
        $this->accounts[$name] = new AccountRef(id: $account->id, slug: 'acc-'.$name, status: 'active', features: [], limits: []);
    }

    // the service shape of the 60 consumers: constructor takes the default connection at resolve time
    app()->scoped(MarkerWriter::class, static fn ($app): MarkerWriter => new MarkerWriter($app->make('db')->connection()));
    $this->central = DB::connection('pgsql')->getDatabaseName();
});

afterEach(function (): void {
    endTenantContext();
    DB::purge('tenant');

    // Held writers keep their PDO alive inside reference cycles until the collector runs.
    gc_collect_cycles();

    foreach ($this->databases as $database) {
        DB::connection('pgsql')->statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()', [$database]);
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$database);
    }
});

/** @return array<string, list<string>> markers per database name (central, a, b) */
function markersSB(): array
{
    $rows = [];

    foreach (['central' => test()->central, 'a' => test()->databases['a'], 'b' => test()->databases['b']] as $label => $database) {
        $configuration = DB::connection('pgsql')->getConfig();
        $configuration['database'] = $database;
        $connection = new ConnectionFactory(app())->make($configuration, 'probe-'.$label);

        try {
            $rows[$label] = $connection->table('scope_markers')->orderBy('marker')->pluck('marker')->all();
        } finally {
            $connection->disconnect();
        }
    }

    return $rows;
}

it('a captured writer cannot cross a Tenant generation and fresh resolution returns to the outer account', function (): void {
    $manager = resolve(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $held = resolve(MarkerWriter::class);
    expect($held->database())->toBe($this->databases['a']);

    $manager->runFor($this->accounts['b'], null, function (): void {
        resolve(MarkerWriter::class)->write('inside');
    });

    expect(fn () => $held->write('held'))->toThrow(TenantFailure::class);
    $after = resolve(MarkerWriter::class);
    $after->write('after');

    expect($after)->not->toBe($held)
        ->and(markersSB())->toBe(['central' => [], 'a' => ['after'], 'b' => ['inside']]);
});

it('central → A: a writer built central is not reused inside A (initialize resets scope without any compensating switch)', function (): void {
    $manager = resolve(TenancyManager::class);
    $central = resolve(MarkerWriter::class);
    expect($central->database())->toBe($this->central);

    $manager->initialize($this->accounts['a']);
    $inside = resolve(MarkerWriter::class);
    $inside->write('in-a');

    expect($inside)->not->toBe($central)
        ->and($inside->database())->toBe($this->databases['a'])
        ->and(markersSB())->toBe(['central' => [], 'a' => ['in-a'], 'b' => []]);
});

it('A → central: end() resets scope, the A writer is not reused centrally', function (): void {
    $manager = resolve(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $inA = resolve(MarkerWriter::class);

    $manager->end();
    $central = resolve(MarkerWriter::class);
    $central->write('central');

    expect($central)->not->toBe($inA)
        ->and($central->database())->toBe($this->central)
        ->and(markersSB())->toBe(['central' => ['central'], 'a' => [], 'b' => []]);
});
