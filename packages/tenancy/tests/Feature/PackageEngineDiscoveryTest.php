<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

it('boots the package engine and generates account ids', function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    expect(resolve(TenancyManager::class))->toBeInstanceOf(DatabaseTenancyManager::class);

    $cluster = (string) Str::uuid();
    $identity = (string) Str::uuid();
    DB::table('root_clusters')->insert(['id' => $cluster, 'name' => 'c', 'pg_host_writer' => 'w.test', 'pgbouncer_host' => 'p.test', 'app_db_user' => 'corex']);
    DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
    $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => 'disc_'.bin2hex(random_bytes(4)).'_test', 'slug' => 'acc-disc', 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);

    expect(Str::isUuid($account->id))->toBeTrue();
});
