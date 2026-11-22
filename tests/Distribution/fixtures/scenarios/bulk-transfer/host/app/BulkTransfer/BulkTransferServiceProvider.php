<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\BoxedWorkspaces;
use App\Scenario\ScenarioClock;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Bulk\Contracts\BulkDraftFactory;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkTargetResolver;
use CoreX\Storage\Contracts\TenantStorage;
use Equipment\Registry\TransferBulkAdapter;
use Equipment\Registry\TransferBulkInputs;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Psr\Clock\ClockInterface;

final class BulkTransferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (['budget', 'sink'] as $role) {
            $this->app['config']->set('database.connections.scenario_'.$role, [
                ...$this->app['config']->get('database.connections.pgsql'),
                'database' => getenv('COREX_SCENARIO_DB_'.strtoupper($role)),
            ]);
        }
        $this->app->singleton(ScenarioClock::class);
        $this->app->alias(ScenarioClock::class, ClockInterface::class);
        $this->app->singleton(BoxedWorkspaces::class);
        foreach ([WorkScopeDirectory::class, TenancyManager::class, TenantContextResolver::class] as $contract) {
            $this->app->alias(BoxedWorkspaces::class, $contract);
        }
        $this->app->scoped(BulkAuthority::class);
        $this->app->scoped(BulkDraftFactory::class, BulkDrafts::class);
        $this->app->scoped(BulkTargetResolver::class, BulkDrafts::class);
        foreach ([PrincipalResolver::class, ClientScopeAuthorizer::class, OperationAuthorizer::class, DataScopeAuthorizer::class] as $contract) {
            $this->app->scoped($contract, static fn ($app): BulkAuthority => $app->make(BulkAuthority::class));
        }

        if (getenv('COREX_SCENARIO_VARIANT') === 'equipment') {
            $this->app->scoped(TransferBulkInputs::class, EquipmentInputs::class);
            $this->app->scoped(ExportSchemaWitness::class, EquipmentExportSchema::class);
            $this->app->scoped(TenantStorage::class, PrivateStorage::class);
        }
    }

    public function boot(): void
    {
        $this->app->extend(ModuleDiscovery::class, static fn (ModuleDiscovery $installed): BulkDeclaration => new BulkDeclaration($installed));
        $this->app->make(Kernel::class)->prependMiddleware(BulkRequest::class);

        if (getenv('COREX_SCENARIO_VARIANT') === 'equipment') {
            // Keep the equipment adapter as the authority and execution implementation.
            $this->app->scoped(BulkExecutor::class, static fn ($app): ObservedExecutor => new ObservedExecutor($app->make(TransferBulkAdapter::class)));
        }
    }
}
