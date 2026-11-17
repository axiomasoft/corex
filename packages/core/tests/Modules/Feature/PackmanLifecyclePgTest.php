<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Settings\Contracts\SettingsRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Extensions\Contracts\Configuration\WithDatabaseSettings;
use Packman\Features\Data\FeatureFlagDefinition;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Data\Migration\SchemaSet;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;
use Packman\Settings\Contracts\SettingDefaultsProviderInterface;

uses()->group('pg');

final class CoreXPackmanIntegrationModule extends ModuleDefinition implements WithDatabaseSettings
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/packman-integration')
            ->title('CoreX Packman Integration')
            ->tablePrefix('pmi')
            ->featureFlags([FeatureFlagDefinition::make('pmi.enabled', default: true)->module('fixture/packman-integration')]);
    }

    public function settingsNamespace(): string
    {
        return 'pmi';
    }

    public function defaultSettings(): array
    {
        return ['label' => 'From manifest', 'secret' => 'test-secret'];
    }

    public function sensitiveSettings(): array
    {
        return ['secret'];
    }
}

it('runs schema claims and lifecycle through Packman while CoreX persists feature defaults and settings', function (): void {
    config(['database.default' => 'pgsql']);
    $database = DB::connection('pgsql');
    $expected = getenv('PGSQL_DATABASE') ?: 'corex_test';

    if (! str_ends_with($expected, '_test') || $database->getDatabaseName() !== $expected || ! app()->environment('testing')) {
        throw new RuntimeException('Packman integration requires APP_ENV=testing and the explicit isolated PGSQL_DATABASE ending in _test.');
    }
    $database->statement('DROP SCHEMA IF EXISTS public CASCADE');
    $database->statement('CREATE SCHEMA public');

    $original = $this->app->basePath();
    $temporary = sys_get_temp_dir().'/corex-packman-lifecycle-'.bin2hex(random_bytes(12));
    $files = new Filesystem;
    $files->makeDirectory($temporary.'/vendor/composer', 0700, true);
    $files->makeDirectory($temporary.'/bootstrap/cache', 0700, true);
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 5).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $metadata['packages'] = array_values(array_filter($metadata['packages'], static fn (array $package): bool => ! in_array($package['type'] ?? 'library', ['packman-module', 'packman-plugin', 'module', 'plugin'], true)));
    $metadata['packages'][] = [
        'name' => 'fixture/packman-integration', 'version' => '1.0.0', 'type' => 'packman-module',
        'extra' => ['packman' => ['module' => CoreXPackmanIntegrationModule::class]],
    ];
    $files->put($temporary.'/vendor/composer/installed.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    $this->app->setBasePath($temporary);
    $this->app->instance('test.packman.schema', new class implements SchemaSetProvider
    {
        public function sets(): array
        {
            return [new SchemaSet(
                package: 'fixture/packman-integration', name: 'tenant', scope: SchemaScope::Tenant,
                path: dirname(__DIR__, 2).'/Fixtures/Packman/migrations', version: 1,
                lifecycle: SchemaLifecycle::Module,
            )];
        }
    });
    $this->app->tag(['test.packman.schema'], SchemaSetProvider::class);
    $this->app->forgetInstance(SchemaSets::class);
    $this->app->forgetInstance(SchemaMigrator::class);
    $this->app->forgetInstance(ModuleRegistry::class);

    try {
        expect(Artisan::call('modules:compile'))->toBe(0, Artisan::output());
        $schemas = resolve(SchemaMigrator::class);
        $schemas->migrateSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant'], 'pgsql');
        expect(Schema::hasTable('pmi_records'))->toBeFalse();

        $lifecycle = resolve(ModuleLifecycle::class);
        $lifecycle->enable('fixture/packman-integration');
        $context = resolve(TenantContextResolver::class)->current();
        expect($lifecycle->status('fixture/packman-integration')->state)->toBe(ModuleState::Enabled)
            ->and(Schema::hasTable('pmi_records'))->toBeTrue()
            ->and(resolve(ModuleRegistry::class)->isActive('fixture/packman-integration', $context))->toBeTrue()
            ->and(resolve(FeatureFlags::class)->active('pmi.enabled'))->toBeTrue()
            ->and(JsonDocuments::decode(resolve(SettingsRepository::class)->get('pmi', 'label')))->toBe('From manifest')
            ->and(resolve(SettingDefaultsProviderInterface::class)->isSensitive('pmi', 'secret'))->toBeTrue()
            ->and($database->table('mod_records')->where('module_name', 'fixture/packman-integration')->where('record_type', 'table')->where('reference', 'pmi_records')->exists())->toBeTrue()
            ->and($database->table('mod_lifecycle_log')->value('actor_type'))->toBe('system');

        $lifecycle->disable('fixture/packman-integration', DisableReason::Manual);
        expect(resolve(ModuleRegistry::class)->isActive('fixture/packman-integration', $context))->toBeFalse();
        $lifecycle->uninstall('fixture/packman-integration', keepData: false);
        expect(Schema::hasTable('pmi_records'))->toBeFalse()
            ->and($lifecycle->status('fixture/packman-integration')->state)->toBe(ModuleState::Purged);
    } finally {
        $this->app->setBasePath($original);
        $files->deleteDirectory($temporary);
    }
});
