<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH = __DIR__.'/../../database/migrations/root';

/** @return list<string> */
function rootTableNames(): array
{
    return [
        'root_clusters',
        'root_templates',
        'root_identities',
        'root_accounts',
        'root_identity_accounts',
        'root_account_products',
        'root_domains',
        'root_oauth_clients',
        'root_idp_sessions',
        'root_oauth_refresh_tokens',
        'root_impersonation_grants',
        'root_feature_flags',
        'root_usage_snapshots',
        'root_migration_batches',
        'root_account_schema_state',
        'root_account_events',
        'root_account_exports',
    ];
}

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

function makeCluster(): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $id,
        'name' => 'cell-'.$id,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer.local',
        'app_db_user' => 'app',
    ]);

    return $id;
}

function makeIdentity(?string $email = null): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert([
        'id' => $id,
        'email' => $email ?? $id.'@example.test',
    ]);

    return $id;
}

function makeAccount(string $clusterId, string $ownerIdentityId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('root_accounts')->insert(array_merge([
        'id' => $id,
        'slug' => 'acct-'.$id,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_'.str_replace('-', '', $id),
        'status' => 'active',
        'owner_identity_id' => $ownerIdentityId,
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ], $overrides));

    return $id;
}

it('creates all 17 root_ tables on the central connection', function (): void {
    foreach (rootTableNames() as $table) {
        expect(DB::connection('pgsql')->getSchemaBuilder()->hasTable($table))
            ->toBeTrue("expected {$table} to exist");
    }
});

it('rejects an invalid account status', function (): void {
    $clusterId = makeCluster();
    $identityId = makeIdentity();

    expect(fn () => makeAccount($clusterId, $identityId, ['status' => 'foo']))
        ->toThrow(QueryException::class);
});

it('enforces the pending-slug and pending-owner table checks', function (): void {
    $clusterId = makeCluster();

    // status=active requires slug + owner (CHECK).
    expect(fn () => makeAccount($clusterId, makeIdentity(), ['status' => 'active', 'slug' => null]))
        ->toThrow(QueryException::class);

    // pending accounts may omit both.
    $pendingId = (string) Str::uuid();
    DB::table('root_accounts')->insert([
        'id' => $pendingId,
        'slug' => null,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_'.str_replace('-', '', $pendingId),
        'status' => 'pending',
        'owner_identity_id' => null,
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ]);

    expect(DB::table('root_accounts')->where('id', $pendingId)->exists())->toBeTrue();
});

it('treats NULL account_id as a value in root_feature_flags uniqueness (UNIQUE NULLS NOT DISTINCT)', function (): void {
    DB::table('root_feature_flags')->insert([
        'id' => (string) Str::uuid(),
        'account_id' => null,
        'flag' => 'global.flag',
        'value' => json_encode(true),
    ]);

    expect(fn () => DB::table('root_feature_flags')->insert([
        'id' => (string) Str::uuid(),
        'account_id' => null,
        'flag' => 'global.flag',
        'value' => json_encode(false),
    ]))->toThrow(QueryException::class);
});

it('enforces partial-unique slug and primary-domain constraints', function (): void {
    $clusterId = makeCluster();
    $ownerId = makeIdentity();
    $accountA = makeAccount($clusterId, $ownerId, ['slug' => 'dup-slug']);

    // Same slug on a second (non-deleted) account collides.
    expect(fn () => makeAccount($clusterId, makeIdentity(), ['slug' => 'dup-slug']))
        ->toThrow(QueryException::class);

    // Two primary domains on the same account collide.
    DB::table('root_domains')->insert([
        'id' => (string) Str::uuid(),
        'account_id' => $accountA,
        'host' => 'a.example.test',
        'type' => 'platform',
        'is_primary' => true,
    ]);

    expect(fn () => DB::table('root_domains')->insert([
        'id' => (string) Str::uuid(),
        'account_id' => $accountA,
        'host' => 'b.example.test',
        'type' => 'platform',
        'is_primary' => true,
    ]))->toThrow(QueryException::class);
});

it('cascades identity_accounts on delete and restricts cluster deletion while referenced', function (): void {
    $clusterId = makeCluster();
    $ownerId = makeIdentity();
    $accountId = makeAccount($clusterId, $ownerId);

    DB::table('root_identity_accounts')->insert([
        'id' => (string) Str::uuid(),
        'identity_id' => $ownerId,
        'account_id' => $accountId,
    ]);

    // RESTRICT: the cluster is still referenced by an account.
    expect(fn () => DB::table('root_clusters')->where('id', $clusterId)->delete())
        ->toThrow(QueryException::class);

    // CASCADE: deleting the account drops its identity_accounts row too.
    DB::table('root_accounts')->where('id', $accountId)->delete();

    expect(DB::table('root_identity_accounts')->where('account_id', $accountId)->exists())->toBeFalse();
});

it('accepts any application product slug but keeps the shape invariant (P2.17/D94)', function (): void {
    $accountId = makeAccount(makeCluster(), makeIdentity());

    // The kernel schema no longer enumerates products: an arbitrary slug from
    // the consuming application is accepted (the admissible SET lives there).
    DB::table('root_account_products')->insert([
        'account_id' => $accountId,
        'product' => 'demo-product',
    ]);

    expect(DB::table('root_account_products')->where('account_id', $accountId)->where('product', 'demo-product')->exists())
        ->toBeTrue();

    // …but the invariant was not weakened away: the value must be a non-empty
    // lowercase slug (empty string and free-form text are still rejected).
    expect(fn () => DB::table('root_account_products')->insert([
        'account_id' => $accountId,
        'product' => '',
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('root_account_products')->insert([
        'account_id' => $accountId,
        'product' => 'Demo Product',
    ]))->toThrow(QueryException::class);
});

it('carries table/column comments (AC-32)', function (): void {
    $comment = DB::selectOne(
        "SELECT obj_description('root_clusters'::regclass) AS comment",
    );

    expect($comment->comment)->not->toBeNull();
});
