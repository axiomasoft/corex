<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\ImportExport\Contracts\ExportPublications;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Internal\Export\CurrentExportSchema;
use CoreX\ImportExport\Internal\Export\SqlExportPublications;
use CoreX\ImportExport\Internal\Transfer\SqlTransferResultReader;
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
        $app->scoped(TransferModuleReferences::class, static fn (Application $app): TransferModuleReferences => new TransferModuleReferences(
            connections: $app->make(DatabaseManager::class), current: $app->make(CurrentWorkScope::class),
        ));
        $app->tag([TransferModuleReferences::class], ModuleReferences::class);
        $app->scoped(TransferReferences::class, static fn (Application $app): TransferReferences => new TransferReferences(
            admission: $app->make(ReferenceAdmission::class), effects: $app->make(ProviderEffectLedger::class), tickets: $app->make(ScopeAdmission::class), clock: $app->make(ClockInterface::class),
        ));
        $app->scoped(CurrentExportSchema::class, static fn (Application $app): CurrentExportSchema => new CurrentExportSchema(
            static fn (): ?ExportSchemaWitness => $app->bound(ExportSchemaWitness::class) ? $app->make(ExportSchemaWitness::class) : null,
        ));
        $app->scoped(TransferResultReader::class, static fn (Application $app): TransferResultReader => new SqlTransferResultReader(
            connection: $app->make(DatabaseManager::class)->connection(),
            authorizer: $app->make(DataScopeAuthorizer::class),
            runtime: new SessionRuntime($app->make(CurrentWorkScope::class)),
            storage: $app->make(TenantStorage::class),
            schema: $app->make(CurrentExportSchema::class),
        ));
        $app->scoped(ExportPublications::class, static fn (Application $app): ExportPublications => new SqlExportPublications(
            connection: $app->make(DatabaseManager::class)->connection(),
            storage: $app->make(TenantStorage::class),
            authorizer: $app->make(DataScopeAuthorizer::class),
            runtime: new SessionRuntime($app->make(CurrentWorkScope::class)),
            references: $app->make(TransferReferences::class),
            clock: $app->make(ClockInterface::class),
            schema: $app->make(CurrentExportSchema::class),
        ));
        $app->scoped(ImportExportFactory::class, static function (Application $app): SqlImportExportFactory {
            $options = $app->make(ImportExportOptions::class);

            return new SqlImportExportFactory(
                database: $app->make(DatabaseManager::class),
                runtime: new SessionRuntime($app->make(CurrentWorkScope::class)),
                authorizer: $app->make(DataScopeAuthorizer::class),
                clock: $app->make(ClockInterface::class),
                storage: $app->make(TenantStorage::class),
                references: $app->make(TransferReferences::class),
                schema: $app->make(CurrentExportSchema::class),
                limits: new TransferLimits(maxRecordBytes: $options->limitsMaxRecordBytes, maxFields: $options->limitsMaxFields, batchRows: $options->limitsBatchRows, maxMemoryMiB: $options->limitsMaxMemoryMiB),
            );
        });
    }
}
