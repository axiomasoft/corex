<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Runtime\Internal\CentralTemplateConnectionResolver;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Tests\Support\Scope\MarkerWriter;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(TestCase::class)->group('pg');

/**
 * D95 п.3 on real databases: the scope boundary is the tenant boundary. Two account databases and
 * the central one each own a `scope_markers` table; a scoped service resolved in a context writes
 * only there, and never into a database of another context.
 */
beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->artisan('migrate', ['--database' => 'pgsql', '--path' => realpath(__DIR__.'/../../database/migrations/root'), '--realpath' => true, '--force' => true])->run();
    DB::statement('CREATE TABLE scope_markers (marker text NOT NULL)');

    $this->databases = [];
    $this->accounts = [];

    foreach (['a', 'b'] as $name) {
        $database = 'corex_sb_'.$name.'_'.bin2hex(random_bytes(5)).'_test';
        $this->databases[$name] = $database;
        DB::statement('CREATE DATABASE '.$database);
        $configuration = DB::connection('pgsql')->getConfig();
        $configuration['database'] = $database;
        $connection = (new ConnectionFactory(app()))->make($configuration, $database);

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
        $connection = (new ConnectionFactory(app()))->make($configuration, 'probe-'.$label);

        try {
            $rows[$label] = $connection->table('scope_markers')->orderBy('marker')->pluck('marker')->all();
        } finally {
            $connection->disconnect();
        }
    }

    return $rows;
}

it('a service resolved before a runFor keeps its database, the one resolved inside writes to the other, and re-resolution after is back (D95 п.3)', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $held = app(MarkerWriter::class);
    expect($held->database())->toBe($this->databases['a']);

    $manager->runFor($this->accounts['b'], null, function (): void {
        app(MarkerWriter::class)->write('inside');
    });

    $held->write('held');
    $after = app(MarkerWriter::class);
    $after->write('after');

    expect($after)->not->toBe($held)
        ->and(markersSB())->toBe(['central' => [], 'a' => ['after', 'held'], 'b' => ['inside']]);
});

it('central → A: a writer built central is not reused inside A (initialize resets scope without any compensating switch)', function (): void {
    $manager = app(TenancyManager::class);
    $central = app(MarkerWriter::class);
    expect($central->database())->toBe($this->central);

    $manager->initialize($this->accounts['a']);
    $inside = app(MarkerWriter::class);
    $inside->write('in-a');

    expect($inside)->not->toBe($central)
        ->and($inside->database())->toBe($this->databases['a'])
        ->and(markersSB())->toBe(['central' => [], 'a' => ['in-a'], 'b' => []]);
});

it('A → central: end() resets scope, the A writer is not reused centrally', function (): void {
    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $inA = app(MarkerWriter::class);

    $manager->end();
    $central = app(MarkerWriter::class);
    $central->write('central');

    expect($central)->not->toBe($inA)
        ->and($central->database())->toBe($this->central)
        ->and(markersSB())->toBe(['central' => ['central'], 'a' => [], 'b' => []]);
});

it('transfers central URL credentials and options to the account connection and leaves the central config untouched', function (): void {
    $central = DB::connection('pgsql')->getConfig();
    $url = sprintf(
        'postgresql://%s:%s@%s:%s/%s?application_name=url-central',
        rawurlencode((string) $central['username']),
        rawurlencode((string) $central['password']),
        $central['host'],
        $central['port'],
        $central['database'],
    );
    $urlOnly = ['driver' => 'pgsql', 'url' => $url, 'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'timezone' => 'UTC'];
    config(['database.connections.pgsql' => $urlOnly]);
    DB::purge('pgsql');
    $before = config('database.connections.pgsql');

    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);
    $tenant = config('database.connections.tenant');
    app(MarkerWriter::class)->write('only-a');

    expect($tenant)->not->toHaveKey('url')
        ->and($tenant['database'])->toBe($this->databases['a'])
        ->and($tenant['username'])->toBe($central['username'])
        ->and($tenant['password'])->toBe($central['password'])
        ->and($tenant['application_name'])->toBe('url-central')
        ->and(markersSB())->toBe(['central' => [], 'a' => ['only-a'], 'b' => []]);

    $manager->end();

    expect(config('database.connections.pgsql'))->toBe($before);
});

it('keeps a host resolver\'s credential/option overrides and refuses routing options', function (): void {
    $central = DB::connection('pgsql')->getConfig();
    $resolver = new class($central) implements AccountConnectionResolver
    {
        public array $options = ['application_name' => 'resolver-name'];

        public function __construct(private readonly array $central) {}

        public function resolve(AccountRef $account): DatabaseConnectionConfig
        {
            $database = DB::connection('pgsql')->table('root_accounts')->where('id', $account->id)->value('db_name');

            return new DatabaseConnectionConfig(
                driver: 'pgsql',
                host: (string) $this->central['host'],
                port: (int) $this->central['port'],
                database: (string) $database,
                username: (string) $this->central['username'],
                options: $this->options + ['password' => $this->central['password']],
            );
        }
    };
    app()->instance(AccountConnectionResolver::class, $resolver);
    app()->forgetInstance(DatabaseTenancyManager::class);
    $manager = app(TenancyManager::class);

    $manager->initialize($this->accounts['a']);
    expect(config('database.connections.tenant.application_name'))->toBe('resolver-name');
    $manager->end();

    foreach (['host', 'database', 'read', 'write', 'url', 'port', 'driver', 'username'] as $routing) {
        $resolver->options = [$routing => 'evil'];
        expect(fn () => $manager->initialize($this->accounts['b']))->toThrow(InvalidConfiguration::class, 'routing keys in options');
        expect($manager->initialized())->toBeFalse()->and(DB::getDefaultConnection())->toBe('pgsql');
    }
});

it('a replica overlay on the central connection never overrides the account route', function (): void {
    $central = DB::connection('pgsql')->getConfig();
    // overlays on the real host, so the central lookups keep working; the point is that they never reach the account route
    config(['database.connections.pgsql.read' => ['host' => $central['host']], 'database.connections.pgsql.write' => ['host' => $central['host']]]);
    DB::purge('pgsql');

    $manager = app(TenancyManager::class);
    $manager->initialize($this->accounts['a']);

    expect(config('database.connections.tenant'))->not->toHaveKeys(['read', 'write'])
        ->and(config('database.connections.tenant.host'))->toBe($central['host']);
});

it('the stock resolver is bound as the account connection resolver', function (): void {
    expect(app(AccountConnectionResolver::class))->toBeInstanceOf(CentralTemplateConnectionResolver::class);
});
