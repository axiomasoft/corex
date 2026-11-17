<?php

declare(strict_types=1);

use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('keeps an unqualified template row untrusted until its physical owner attests it', function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    $id = (string) Str::uuid();
    DB::table('root_templates')->insert([
        'id' => $id, 'version' => 99, 'db_name' => 'unqualified_template_test', 'status' => 'ready',
        'embedding_model' => 'bge-m3', 'embedding_dim' => 1024,
    ]);

    expect(DB::table('root_templates')->where('id', $id)->value('schema_hash'))->toBeNull();
    expect(fn () => resolve(TemplateIntegrityChecks::class)->verifyCurrent(99))->toThrow(TemplateIntegrityException::class);
});
