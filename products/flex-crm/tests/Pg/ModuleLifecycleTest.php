<?php

declare(strict_types=1);

use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Enums\DisableReason;
use Flex\Crm\CrmModule;
use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

it('discovers CRM composer metadata and manages its schema through the standard lifecycle', function (): void {
    config(['database.default' => 'pgsql', 'corex.modules.connection' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    expect(Schema::hasTable('crm_companies'))->toBeFalse();
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../vendor/axiomasoft/corex/database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();

    $consumer = database_path('consumer');
    mkdir($consumer.'/vendor/composer', 0700, true);
    symlink(dirname(__DIR__, 2), $consumer.'/vendor/crm');
    $metadata = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $metadata['version'] = 'dev-main';
    $metadata['install-path'] = '../crm';
    file_put_contents($consumer.'/vendor/composer/installed.json', json_encode(['packages' => [$metadata]], JSON_THROW_ON_ERROR));
    $databasePath = database_path();
    $bootstrapPath = app()->bootstrapPath();
    $this->app->setBasePath($consumer);
    $this->app->useDatabasePath($databasePath);
    $this->app->useBootstrapPath($bootstrapPath);
    $discovered = (new ModuleDiscovery)->discover();
    expect($discovered)->toHaveCount(1)->and($discovered[0]->composerName)->toBe('flex/crm')
        ->and($metadata['extra']['corex']['module'])->toBe(CrmModule::class);
    $this->artisan('corex:modules:compile')->assertSuccessful();
    $this->app->forgetInstance(ModuleRegistry::class);
    $this->app->forgetInstance(ModuleLifecycle::class);
    $lifecycle = app(ModuleLifecycle::class);
    $lifecycle->enable('flex/crm');
    foreach (['crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company', 'crm_pipelines', 'crm_stages', 'crm_deals', 'crm_leads', 'crm_activities'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    $migrations = DB::table('migrations')->count();
    $id = (string) Str::uuid7();
    DB::table('crm_companies')->insert(['id' => $id, 'name' => 'Preserved', 'created_by' => json_encode(['source' => 'system', 'id' => 'lifecycle-test', 'name' => 'Lifecycle fixture'], JSON_THROW_ON_ERROR)]);
    expect(Schema::hasTable('crm_activities'))->toBeTrue()
        ->and(DB::table('mod_records')->where('module_name', 'flex/crm')->count())->toBeGreaterThan(0);
    $lifecycle->disable('flex/crm', DisableReason::Manual);
    expect(Schema::hasTable('crm_activities'))->toBeTrue();
    $lifecycle->enable('flex/crm');
    expect(DB::table('mod_modules')->where('name', 'flex/crm')->value('state'))->toBe('enabled');
    expect(DB::table('crm_companies')->where('id', $id)->value('name'))->toBe('Preserved')->and(DB::table('migrations')->count())->toBe($migrations);
    $lifecycle->enable('flex/crm');
    expect(DB::table('migrations')->count())->toBe($migrations);
});
