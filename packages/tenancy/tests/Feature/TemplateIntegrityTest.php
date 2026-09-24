<?php

declare(strict_types=1);

use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityException;
use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_TEMPLATE_INTEGRITY = __DIR__.'/../../database/migrations/root';
const TEMPLATE_INTEGRITY_DB = 'tpl_integrity_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_TEMPLATE_INTEGRITY),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    DB::statement('DROP DATABASE IF EXISTS '.TEMPLATE_INTEGRITY_DB);
    DB::statement('CREATE DATABASE '.TEMPLATE_INTEGRITY_DB);

    $template = templateIntegrityConnection();
    $template->statement('CREATE TABLE integrity_markers (id integer PRIMARY KEY, note text NOT NULL)');
    DB::disconnect('template_integrity');
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::statement('DROP DATABASE IF EXISTS '.TEMPLATE_INTEGRITY_DB);
});

function templateIntegrityConnection(): Connection
{
    $configuration = DB::connection('pgsql')->getConfig();
    $configuration['database'] = TEMPLATE_INTEGRITY_DB;

    config(['database.connections.template_integrity' => $configuration]);
    DB::purge('template_integrity');

    return DB::connection('template_integrity');
}

function insertTrustedIntegrityTemplate(): array
{
    $clusterId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => "integrity-{$clusterId}",
        'status' => 'active',
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer.local',
        'app_db_user' => 'corex',
        'template_version' => 1,
    ]);
    DB::table('root_templates')->insert([
        'id' => $templateId,
        'version' => 1,
        'db_name' => TEMPLATE_INTEGRITY_DB,
        'status' => 'ready',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'built_at' => now(),
    ]);
    TestCase::trustTemplate(templateId: $templateId, database: TEMPLATE_INTEGRITY_DB);

    return [$clusterId, $templateId];
}

it('accepts a trusted expected, recorded, and actual schema-hash triple', function (): void {
    [$clusterId, $templateId] = insertTrustedIntegrityTemplate();

    $template = app(TemplateIntegrityVerifier::class)->assertCurrent(
        version: 1,
        clusterId: $clusterId,
    );

    expect($template->id)->toBe($templateId);
});

it('keeps a legacy template row nullable and therefore untrusted', function (): void {
    $templateId = (string) Str::uuid();

    DB::table('root_templates')->insert([
        'id' => $templateId,
        'version' => 99,
        'db_name' => TEMPLATE_INTEGRITY_DB,
        'status' => 'ready',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ]);

    expect(DB::table('root_templates')->where('id', $templateId)->value('schema_hash'))->toBeNull();
});

it('rejects catalogue drift after a template was sealed', function (): void {
    [$clusterId] = insertTrustedIntegrityTemplate();
    templateIntegrityConnection()->statement('ALTER TABLE integrity_markers ADD COLUMN drifted boolean NOT NULL DEFAULT false');
    DB::disconnect('template_integrity');

    expect(fn () => app(TemplateIntegrityVerifier::class)->assertCurrent(
        version: 1,
        clusterId: $clusterId,
    ))->toThrow(TemplateIntegrityException::class);
});
