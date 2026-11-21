<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Bulk\Internal\BulkFailureReader;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Bulk\Internal\BulkResultReader;
use CoreX\Operations\Bulk\Internal\BulkRetention;
use CoreX\Operations\Bulk\Internal\BulkWorker;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Internal\TransferCapabilityUnavailable;
use CoreX\Operations\Internal\OperationsModuleReferences;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BulkBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $options = BulkOptionsParser::fromArray((array) $app->make(Repository::class)->get('corex-operations.bulk', []));

        $app->scoped(OperationsModuleReferences::class, static fn (Application $app): OperationsModuleReferences => new OperationsModuleReferences(
            $app->make(ApiSchemaConnections::class), $app->make(CurrentWorkScope::class),
        ));
        $app->tag([OperationsModuleReferences::class], ModuleReferences::class);

        $app->singleton(BulkOptions::class, static fn (Application $app): BulkOptions => BulkOptionsParser::fromArray(
            (array) $app->make(Repository::class)->get('corex-operations.bulk', []),
        ));
        $app->scoped(BulkRetention::class, static fn (Application $app): BulkRetention => new BulkRetention(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            scopeCodec: $app->make(DataScopeCodec::class),
            contexts: $app->make(CurrentWorkScope::class),
            clock: $app->make(ClockInterface::class),
            resultRetentionSeconds: $app->make(BulkOptions::class)->resultRetentionSeconds,
            metadataRetentionSeconds: $app->make(BulkOptions::class)->metadataRetentionSeconds,
            idempotencyRetentionSeconds: $app->make(BulkOptions::class)->idempotencyRetentionSeconds,
            admission: $app->make(ReferenceAdmission::class),
        ));

        if (! $options->enabled) {
            return;
        }

        $app->scoped(BulkOperationStore::class, static fn (Application $app): SqlBulkOperationStore => new SqlBulkOperationStore(
            connection: $app->make(ApiSchemaConnections::class)->tenant(),
            scopeCodec: $app->make(DataScopeCodec::class),
            contexts: $app->make(CurrentWorkScope::class),
            clock: $app->make(ClockInterface::class),
            leaseSeconds: $app->make(BulkOptions::class)->leaseSeconds,
            resultRetentionSeconds: $app->make(BulkOptions::class)->resultRetentionSeconds,
            admission: $app->make(ReferenceAdmission::class),
        ));
        $app->scoped(SubmitBulkOperation::class, static fn (Application $app): SubmitBulkOperation => new SubmitBulkOperation(
            $app, $app->make(BulkOperationStore::class), $app->make(ApiSchemaConnections::class),
            $app->make(BulkOptions::class), self::submissionSecret($app), $app->make(ReferenceAdmission::class),
        ));
        $app->scoped(BulkWorker::class, static fn (Application $app): BulkWorker => new BulkWorker(
            store: $app->make(BulkOperationStore::class),
            executor: static fn (): BulkExecutor => $app->bound(BulkExecutor::class) ? $app->make(BulkExecutor::class) : throw new TransferCapabilityUnavailable,
            clock: $app->make(ClockInterface::class),
            worker: $app->make(BulkOptions::class)->worker,
            retrySeconds: $app->make(BulkOptions::class)->retrySeconds,
            maxRecoveryAttempts: $app->make(BulkOptions::class)->maxRecoveryAttempts,
        ));
        $app->scoped(BulkResultReader::class, static fn (Application $app): BulkResultReader => new BulkResultReader(
            static fn (): ?BulkOwner => self::owner($app),
        ));
        $app->scoped(BulkFailureReader::class, static fn (Application $app): BulkFailureReader => new BulkFailureReader(
            static fn (): ?BulkOwner => self::owner($app),
        ));
        $app->scoped(BulkOperations::class, static fn (Application $app): BulkOperations => new BulkOperations(
            store: $app->make(BulkOperationStore::class),
            results: $app->make(BulkResultReader::class),
            failures: $app->make(BulkFailureReader::class),
            clock: $app->make(ClockInterface::class),
            allowed: static fn (DataScope $scope, BulkOperationDefinition $definition): bool => $app->make(BulkReadAdmission::class)->allows($scope, $definition),
        ));
    }

    #[Override]
    public function boot(Application $app): void {}

    private static function owner(Application $app): ?BulkOwner
    {
        return $app->bound(BulkOwner::class) ? $app->make(BulkOwner::class) : null;
    }

    private static function submissionSecret(Application $app): ?string
    {
        $secret = $app->make(Repository::class)->get('app.key');

        return is_string($secret) ? $secret : null;
    }
}
