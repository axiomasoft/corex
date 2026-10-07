<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Internal\Models\Account;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const TENANT_DB_QTR = 'acc_qtr_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_QTR);
    DB::statement('CREATE DATABASE '.TENANT_DB_QTR);
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::purge('tenant');
    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_QTR);
});

/**
 * F-Q1 (D76 PoC finding): a job that throws and is released for retry must not leak its tenant
 * context into the next job of the same long-lived worker. The own engine ends a job's context at
 * its single completion point — `JobAttempted`, dispatched from the `finally` of Worker::process —
 * so a throwing job, a released job and a failed job are all covered by one event; the intermediate
 * `JobExceptionOccurred`/`JobFailed` do not end it (failed callbacks still run in the job context).
 * Exercised against the real events, without a full queue:work cycle.
 */
it('ends tenancy on JobAttempted after a throwing job so the next job starts in central context (AC-28 е)', function (): void {
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
    $manager = resolve(TenancyManager::class);
    $failure = new RuntimeException('job blew up');

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn(['corex_tenant' => ['account' => $account->id, 'workspace' => null]]);
    Event::dispatch(new JobProcessing('database', $job));

    expect($manager->initialized())->toBeTrue()
        ->and(DB::getDefaultConnection())->toBe('tenant');

    // The retry/exception notifications leave the job context alone ...
    Event::dispatch(new JobExceptionOccurred('database', $job, $failure));
    expect($manager->initialized())->toBeTrue();

    // ... and the single completion point ends it.
    Event::dispatch(new JobAttempted('database', $job, $failure));

    expect($manager->initialized())->toBeFalse()
        ->and($manager->context())->toBeNull()
        ->and(DB::getDefaultConnection())->toBe('pgsql');

    $next = Mockery::mock(Job::class);
    $next->shouldReceive('payload')->andReturn([]);
    Event::dispatch(new JobProcessing('database', $next));
    expect($manager->initialized())->toBeFalse();
    Event::dispatch(new JobAttempted('database', $next));
});
