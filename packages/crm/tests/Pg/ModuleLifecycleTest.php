<?php

declare(strict_types=1);

use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Enums\DisableReason;
use Flex\Crm\CrmModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses()->group('pg');

it('discovers CRM composer metadata and manages its schema through the standard lifecycle', function (): void {
    config(['database.default' => 'pgsql', 'corex-modules.connection' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../vendor/corex/modules/database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();

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
    $lifecycle = app(ModuleLifecycle::class);
    $lifecycle->enable('flex/crm');
    expect(Schema::hasTable('crm_activities'))->toBeTrue()
        ->and(DB::table('mod_records')->where('module_name', 'flex/crm')->count())->toBeGreaterThan(0);
    $lifecycle->disable('flex/crm', DisableReason::Manual);
    expect(Schema::hasTable('crm_activities'))->toBeTrue();
    $lifecycle->enable('flex/crm');
    expect(DB::table('mod_modules')->where('name', 'flex/crm')->value('state'))->toBe('enabled');
});
