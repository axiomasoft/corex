<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/** Composes the public session factory with the package-owned executors. */
#[Internal]
final class ImportExportBindings
{
    public static function register(Application $app): void
    {
        $app->scoped(ImportExportFactory::class, static function (Application $app): SqlImportExportFactory {
            $options = $app->make(ImportExportOptions::class);

            return new SqlImportExportFactory(
                database: $app->make(DatabaseManager::class),
                runtime: new SessionRuntime($app->make(CurrentWorkScope::class)),
                authorizer: $app->make(DataScopeAuthorizer::class),
                clock: $app->make(ClockInterface::class),
                storage: $app->make(TenantStorage::class),
                limits: new TransferLimits(maxRecordBytes: $options->limitsMaxRecordBytes, maxFields: $options->limitsMaxFields, batchRows: $options->limitsBatchRows, maxMemoryMiB: $options->limitsMaxMemoryMiB),
            );
        });
    }
}
