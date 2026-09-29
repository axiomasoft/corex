<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Models\Account;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_QTR = __DIR__.'/../../database/migrations/root';

const TENANT_DB_QTR = 'acc_qtr_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_QTR),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_QTR);
    DB::statement('CREATE DATABASE '.TENANT_DB_QTR);
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::purge('tenant');
    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_QTR);
});

/**
 * F-Q1 (D76 PoC finding): stancl's QueueTenancyBootstrapper reverts tenancy
 * on JobProcessed/JobFailed only — a job that throws and is released for
 * retry fires JobExceptionOccurred instead, leaking the tenant context into
 * the next job picked up by the same long-lived worker. This exercises the
 * app-listener mitigation directly against the real event, without needing
 * a full queue:work retry cycle (already proven end-to-end in the P2.1 PoC,
 * findings/P2.1-poc-report-2026-07-18.md §F-Q1).
 */
it('ends tenancy on JobExceptionOccurred so the next job in the worker starts in central context (AC-28 е)', function (): void {
    $clusterId = (string) Str::uuid();
    DB::table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => 'cell-qtr',
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
    ]);

    $identityId = (string) Str::uuid();
    DB::table('root_identities')->insert([
        'id' => $identityId,
        'email' => $identityId.'@example.test',
    ]);

    $account = Account::on('pgsql')->create([
        'cluster_id' => $clusterId,
        'db_name' => TENANT_DB_QTR,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        // status=active requires owner_identity_id+slug (root_accounts_*_or_pending_ck).
        'owner_identity_id' => $identityId,
        'status' => 'active',
    ]);

    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->initialize(new AccountRef(
        id: $account->id,
        slug: (string) $account->slug,
        status: $account->status,
        features: [],
        limits: [],
    ));

    expect($manager->initialized())->toBeTrue();

    Event::dispatch(new JobExceptionOccurred('database', null, new RuntimeException('job blew up')));

    expect($manager->initialized())->toBeFalse()
        ->and($manager->context())->toBeNull();
});
