<?php

declare(strict_types=1);

namespace App\Providers;

use App\OwnerEffects\Charge;
use App\OwnerEffects\Effects;
use App\OwnerEffects\MediaInventory;
use App\OwnerEffects\MediaViews;
use App\OwnerEffects\PinJournal;
use App\OwnerEffects\Policies;
use App\OwnerEffects\ResultStorage;
use App\Scenario\Scenario;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Panels\Contracts\SavedViewInventory;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Support\ServiceProvider;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Vaulter\Database\VaulterDatabase;
use Vaulter\Media\Contracts\Cas\BlobPins;

final class OwnerEffectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app['config'];
        $config->set('database.connections.owner_budget', [...$config->get('database.connections.pgsql'), 'database' => getenv('COREX_SCENARIO_DB_BUDGET')]);
        $config->set('queue.default', 'sync');
        $config->set('vaulter.database.connection', null);
        $config->set('vaulter.schedule.enabled', false);
        $config->set('vaulter-media.disk', 'owner_media');
        $config->set('filesystems.disks.owner_media', ['driver' => 'local', 'root' => storage_path('app/owner-effects'), 'throw' => true]);
        $this->app->scoped(MediaPolicy::class, Policies::class);
        $this->app->scoped(LifecycleInventory::class, MediaInventory::class);
        $this->app->scoped(SavedViewInventory::class, MediaViews::class);
        $this->app->scoped(ViewAccessPolicy::class, MediaViews::class);
        $this->app->scoped(TenantStorage::class, ResultStorage::class);
        $this->app->scoped(ExportSchemaWitness::class, Effects::class);
        $this->app->scoped(OperationAuthorizer::class, Charge::class);
        $this->app->extend(BlobPins::class, static fn (BlobPins $real, $app) => new PinJournal($real, $app->make(VaulterDatabase::class)));
        $this->app->scoped(ApiSchemaConnections::class, static function ($app): ApiSchemaConnections {
            $db = $app['db'];
            $schema = $app['config']->get('corex-operations.schema');
            $context = $app->make(TenancyManager::class)->context();

            if ($context !== null && $db->getDefaultConnection() === 'tenant') {
                $role = match ($context->id()) {
                    Scenario::ACCOUNT_A => 'A',
                    Scenario::ACCOUNT_B => 'B',
                };
                $schema['tenant'] = ['connection' => 'tenant', 'driver' => 'pgsql', 'database' => getenv('COREX_SCENARIO_DB_'.$role)];
            }

            return ApiSchemaConnections::fromSchema($schema, $db);
        });
    }
}
