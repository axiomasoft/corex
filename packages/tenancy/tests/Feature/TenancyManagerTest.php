<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const TENANT_DB_TM = 'acc_tm_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    // A real (empty) tenant database — DatabaseTenancyBootstrapper checks
    // existence in local/testing envs; no clone/provisioning pipeline needed
    // for a connection-switch test.
    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_TM);
    DB::statement('CREATE DATABASE '.TENANT_DB_TM);

    config(['session.driver' => 'database']);
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::purge('tenant');
    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_TM);
});

function makeClusterRowTM(): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $id,
        'name' => 'cell-'.$id,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
    ]);

    return $id;
}

function makeIdentityRowTM(): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert([
        'id' => $id,
        'email' => $id.'@example.test',
    ]);

    return $id;
}

function makeAccountTM(string $clusterId, array $overrides = []): Account
{
    return Account::on('pgsql')->create(array_merge([
        'cluster_id' => $clusterId,
        'db_name' => TENANT_DB_TM,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        // status=active requires owner_identity_id+slug (root_accounts_*_or_pending_ck).
        'owner_identity_id' => makeIdentityRowTM(),
        'status' => 'active',
    ], $overrides));
}

it('cloud initialize()/end() switches connection+session and context() reflects the active account (AC-28 а)', function (): void {
    $account = makeAccountTM(makeClusterRowTM());
    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);

    /** @var DatabaseTenancyManager $manager */
    $manager = resolve(TenancyManager::class);

    expect($manager->initialized())->toBeFalse()
        ->and($manager->context())->toBeNull();

    $reader = new class
    {
        public function currentDefault(): string
        {
            return DB::getDefaultConnection();
        }
    };

    expect($reader->currentDefault())->toBe('pgsql');

    $manager->initialize($ref);

    expect($manager->initialized())->toBeTrue()
        ->and($reader->currentDefault())->toBe('tenant')
        ->and(DB::connection('tenant')->getDatabaseName())->toBe(TENANT_DB_TM)
        ->and(config('session.connection'))->toBe('tenant');

    $ctx = $manager->context();
    expect($ctx)->not->toBeNull()
        ->and($ctx->account->id)->toBe($account->id)
        ->and($ctx->workspace)->toBeNull()
        ->and($ctx->isBoxed())->toBeFalse();

    $workspace = new WorkspaceRef(id: (string) Str::uuid(), slug: 'ws', type: 'department', path: '/ws', parentId: null);
    $manager->setWorkspace($workspace);
    expect($manager->context()->workspace)->toBe($workspace);

    $manager->end();

    expect($manager->initialized())->toBeFalse()
        ->and($reader->currentDefault())->toBe('pgsql')
        ->and($manager->context())->toBeNull();

    expect($reader->currentDefault())->toBe('pgsql');
});

it('runFor() initializes, runs the closure, and always ends — nesting restores the outer account (AC-28 б)', function (): void {
    $accountA = makeAccountTM(makeClusterRowTM());

    $secondClusterId = makeClusterRowTM();
    DB::statement('DROP DATABASE IF EXISTS acc_tm_test_b');
    DB::statement('CREATE DATABASE acc_tm_test_b');
    $accountB = makeAccountTM($secondClusterId, ['db_name' => 'acc_tm_test_b']);

    $refA = new AccountRef(id: $accountA->id, slug: (string) $accountA->slug, status: $accountA->status, features: [], limits: []);
    $refB = new AccountRef(id: $accountB->id, slug: (string) $accountB->slug, status: $accountB->status, features: [], limits: []);

    /** @var DatabaseTenancyManager $manager */
    $manager = resolve(TenancyManager::class);

    $workspace = new WorkspaceRef(id: (string) Str::uuid(), slug: 'outer', type: 'department', path: '/outer', parentId: null);
    $manager->runFor($refA, $workspace, function () use ($manager, $refA, $refB, $workspace): void {
        expect($manager->context()->account->id)->toBe($refA->id);

        $inner = $manager->runFor($refB, null, fn (): string => $manager->context()->account->id);

        expect($inner)->toBe($refB->id)
            // nesting restored the outer account (AC-28 б), not the central scope.
            ->and($manager->context()->account->id)->toBe($refA->id)
            ->and($manager->context()->workspace)->toBe($workspace);

        expect(fn () => $manager->runFor($refB, null, static fn () => throw new RuntimeException('nested failure')))
            ->toThrow(RuntimeException::class, 'nested failure');
        expect($manager->context()->account->id)->toBe($refA->id)
            ->and($manager->context()->workspace)->toBe($workspace);
    });

    expect($manager->initialized())->toBeFalse();

    DB::purge('tenant');
    DB::statement('DROP DATABASE IF EXISTS acc_tm_test_b');
});

it('a callback that throws inside runFor() still ends tenancy (unconditional revert)', function (): void {
    $account = makeAccountTM(makeClusterRowTM());
    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);

    /** @var DatabaseTenancyManager $manager */
    $manager = resolve(TenancyManager::class);

    expect(fn () => $manager->runFor($ref, null, function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom');

    expect($manager->initialized())->toBeFalse();
});

it('TenancyManagerContextResolver::current() throws CentralContextException outside any tenant (AC-28 в)', function (): void {
    /** @var TenantContextResolver $resolver */
    $resolver = resolve(TenantContextResolver::class);

    expect(fn () => $resolver->current())->toThrow(CentralContextException::class);

    $account = makeAccountTM(makeClusterRowTM());
    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
    /** @var TenancyManager $manager */
    $manager = resolve(TenancyManager::class);
    $manager->initialize($ref);

    expect($resolver->current()->account->id)->toBe($account->id);

    $manager->end();
});

it('is wired via the TenancyManager contract binding, not a hardcoded concrete (D10)', function (): void {
    expect(resolve(TenancyManager::class))->toBeInstanceOf(DatabaseTenancyManager::class);
});
