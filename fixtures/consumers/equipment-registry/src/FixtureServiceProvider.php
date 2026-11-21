<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\ImportExport\Contracts\ExportPublications;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

final class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(OperationsServiceProvider::class);
        $this->app->scoped(TransferBulkAdapter::class, static fn (Application $app): TransferBulkAdapter => new TransferBulkAdapter(
            sessions: $app->make(ImportExportFactory::class),
            results: $app->make(TransferResultReader::class),
            exports: $app->make(ExportPublications::class),
            inputs: $app->bound(TransferBulkInputs::class)
                ? $app->make(TransferBulkInputs::class)
                : throw new ApiError(OperationsErrorCode::CapabilityUnavailable, 503, 'Equipment transfer inputs are unavailable.'),
        ));
        $this->app->alias(TransferBulkAdapter::class, BulkExecutor::class);
        $this->app->alias(TransferBulkAdapter::class, BulkOwner::class);
        $this->app->tag((new RegisteredModule)->schemaSetProviders(), SchemaSetProvider::class);
        $this->app->tag([DeclaredEquipmentFields::class], DeclaredFields::class);
        $this->app->singleton(MemoryDriver::class);
        $this->app->tag(MemoryDriver::class, [CandidateDriver::class, IndexDriver::class]);
        $this->callAfterResolving(SearchDriverRegistry::class, static function (SearchDriverRegistry $registry): void {
            $resolve = static fn (Application $app): MemoryDriver => $app->make(MemoryDriver::class);
            $registry->register(Smoke::Driver, $resolve);
            $registry->registerIndex(Smoke::Driver, $resolve);
        });
    }
}
