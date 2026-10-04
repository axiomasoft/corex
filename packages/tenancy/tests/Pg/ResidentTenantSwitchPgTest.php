<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(TestCase::class)->group('pg');

/**
 * Resident worker: one application serves tenant A, the lifecycle boundary
 * (Application::forgetScopedInstances()) runs, then the same application serves tenant B.
 * The scoped Outbox must be rebuilt against the connection of the tenant that is current
 * *now*; a binding that captured A's connection would write B's message into A's database.
 * The assertions read the physical databases through their own connection names, bypassing
 * every service under test.
 */
function residentSwitchMessage(string $marker): MessageEnvelope
{
    return new MessageEnvelope(
        messageId: (string) Str::uuid7(),
        source: 'axiomasoft/corex',
        name: 'resident.marker',
        contractVersion: 1,
        occurredAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        scope: ['accountId' => 'resident-'.$marker],
        payload: new MessagePayload(['marker' => $marker]),
        piiClass: PiiClass::None,
    );
}

/** @return list<string> markers stored in the outbox table of the database behind $connection */
function residentSwitchMarkers(ConnectionInterface $connection): array
{
    $markers = [];

    foreach ($connection->table(app(CoreOptions::class)->outboxMessagesTable)->orderBy('id')->pluck('payload') as $payload) {
        $markers[] = json_decode((string) $payload, true, flags: JSON_THROW_ON_ERROR)['marker'];
    }

    return $markers;
}

function residentSwitchTenantConnection(string $database): Connection
{
    $configuration = DB::connection('pgsql')->getConfig();
    $configuration['database'] = $database;

    return (new ConnectionFactory(app()))->make($configuration, $database);
}

beforeEach(function (): void {
    expect(app()->environment())->toBe('testing')
        ->and(str_ends_with(DB::connection('pgsql')->getDatabaseName(), '_test'))->toBeTrue();
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->artisan('migrate', ['--database' => 'pgsql', '--path' => realpath(__DIR__.'/../../database/migrations/root'), '--realpath' => true, '--force' => true])->run();
    // The central database carries the kernel tables too: a leaked write lands there and is visible.
    $this->artisan('migrate', ['--database' => 'pgsql', '--path' => realpath(__DIR__.'/../../../core/database/migrations/pg'), '--realpath' => true, '--force' => true])->run();

    $this->databases = [];
    $this->accounts = [];

    foreach (['alpha', 'beta'] as $name) {
        $database = 'corex_p05_resident_'.bin2hex(random_bytes(6)).'_test';
        $this->databases[$name] = $database;
        DB::statement('CREATE DATABASE '.$database);

        $cluster = (string) Str::uuid();
        $identity = (string) Str::uuid();
        DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $name, 'pg_host_writer' => 'writer.test', 'pgbouncer_host' => 'pool.test', 'app_db_user' => 'corex']);
        DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
        $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => $database, 'slug' => $name, 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);
        $this->accounts[$name] = new AccountRef(id: $account->id, slug: $name, status: 'active', features: [], limits: []);
    }

    // Kernel tables inside each tenant database: run the same migration files in that tenant's context.
    foreach ($this->accounts as $account) {
        app(TenancyManager::class)->runFor($account, null, function (): void {
            $this->artisan('migrate', ['--database' => 'tenant', '--path' => realpath(__DIR__.'/../../../core/database/migrations/pg'), '--realpath' => true, '--force' => true])->run();
        });
    }

    app()->instance(MessageContractRegistry::class, new ArrayMessageContractRegistry(contracts: [new MessageContract(
        name: 'resident.marker',
        version: 1,
        class: MessageClass::IntegrationEvent,
        owner: 'axiomasoft/corex',
        maxPiiClass: PiiClass::None,
        channel: 'resident',
    )]));
    app()->forgetScopedInstances();
});

afterEach(function (): void {
    app(TenancyManager::class)->end();
    DB::purge('tenant');

    foreach ($this->databases as $database) {
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$database);
    }
});

it('writes after a lifecycle boundary into the database of the tenant that is current then', function (): void {
    $manager = app(TenancyManager::class);

    $manager->initialize($this->accounts['alpha']);
    $outboxForAlpha = app(Outbox::class);
    DB::transaction(static fn () => $outboxForAlpha->append(residentSwitchMessage('A')));
    $manager->end();

    // What an Octane or queue worker runs between two requests.
    app()->forgetScopedInstances();

    $manager->initialize($this->accounts['beta']);
    $outboxForBeta = app(Outbox::class);
    DB::transaction(static fn () => $outboxForBeta->append(residentSwitchMessage('B')));
    $manager->end();

    $alpha = residentSwitchTenantConnection($this->databases['alpha']);
    $beta = residentSwitchTenantConnection($this->databases['beta']);

    try {
        expect($outboxForBeta)->not->toBe($outboxForAlpha)
            ->and(residentSwitchMarkers($alpha))->toBe(['A'])
            ->and(residentSwitchMarkers($beta))->toBe(['B'])
            ->and(residentSwitchMarkers(DB::connection('pgsql')))->toBe([]);
    } finally {
        $alpha->disconnect();
        $beta->disconnect();
    }
});
