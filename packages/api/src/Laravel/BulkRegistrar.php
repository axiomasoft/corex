<?php

declare(strict_types=1);

namespace CoreX\Api\Laravel;

use CoreX\Api\Contracts\BulkOperationStore;
use CoreX\Api\Contracts\BulkTransferFactory;
use CoreX\Api\Data\BulkOperation;
use CoreX\Api\Data\BulkOptions;
use CoreX\Api\Data\BulkTransferSession;
use CoreX\Api\Internal\Bulk\BulkFailureReader;
use CoreX\Api\Internal\Bulk\BulkOperations;
use CoreX\Api\Internal\Bulk\BulkResultReader;
use CoreX\Api\Internal\Bulk\BulkRetention;
use CoreX\Api\Internal\Bulk\BulkWorker;
use CoreX\Api\Internal\Bulk\SqlBulkOperationStore;
use CoreX\Api\Internal\Bulk\TransferBulkExecutor;
use CoreX\Api\Internal\Bulk\TransferCapabilityUnavailable;
use CoreX\Contracts\DataScopeCodec;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use Illuminate\Contracts\Foundation\Application;
use Psr\Clock\ClockInterface;

/** @internal Explicit, lazy composition root for durable bulk execution. */
final readonly class BulkRegistrar
{
    public static function register(Application $app): void
    {
        $config = $app['config']->get('corex-api.bulk');
        $options = BulkOptions::fromArray(is_array($config) ? $config : []);
        $app->instance(BulkOptions::class, $options);
        $app->singleton(BulkRetention::class, static fn (Application $app): BulkRetention => new BulkRetention(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            scopeCodec: $app->make(DataScopeCodec::class),
            contexts: $app->make(TenantContextRestorer::class),
            clock: $app->make(ClockInterface::class),
            resultRetentionSeconds: $options->resultRetentionSeconds,
            metadataRetentionSeconds: $options->metadataRetentionSeconds,
            idempotencyRetentionSeconds: $options->idempotencyRetentionSeconds,
        ));

        if (! $options->enabled) {
            return;
        }

        $app->singleton(BulkOperationStore::class, static fn (Application $app): SqlBulkOperationStore => new SqlBulkOperationStore(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            scopeCodec: $app->make(DataScopeCodec::class),
            contexts: $app->make(TenantContextRestorer::class),
            clock: $app->make(ClockInterface::class),
            leaseSeconds: $options->leaseSeconds,
            resultRetentionSeconds: $options->resultRetentionSeconds,
        ));
        $app->singleton(TransferBulkExecutor::class, static fn (Application $app): TransferBulkExecutor => new TransferBulkExecutor(
            factory: self::factory($app),
            store: $app->make(BulkOperationStore::class),
            clock: $app->make(ClockInterface::class),
            transferInstalled: interface_exists('CoreX\\Transfer\\Contracts\\TransferRunner'),
            retrySeconds: $options->retrySeconds,
            scopes: $app->make(DataScopeCodec::class),
        ));
        $app->singleton(BulkWorker::class, static fn (Application $app): BulkWorker => new BulkWorker(
            store: $app->make(BulkOperationStore::class),
            executor: $app->make(TransferBulkExecutor::class),
            clock: $app->make(ClockInterface::class),
            worker: $options->worker,
        ));
        $app->singleton(BulkResultReader::class, static fn (Application $app): BulkResultReader => new BulkResultReader(
            // The host must replace this reader with its current field-policy check.
            fieldAccess: static fn (): bool => false,
            manifest: static function (DataScope $scope, BulkOperation $operation) use ($app): array {
                $session = self::session($app, $operation);

                return $session->results->result($scope, $session->transfer);
            },
        ));
        $app->singleton(BulkFailureReader::class, static fn (Application $app): BulkFailureReader => new BulkFailureReader(
            static function (DataScope $scope, BulkOperation $operation, ?string $after, int $limit) use ($app): array {
                $session = self::session($app, $operation);

                return $session->results->failures($scope, $session->transfer, $after, $limit);
            },
        ));
        $app->singleton(BulkOperations::class, static fn (Application $app): BulkOperations => new BulkOperations(
            store: $app->make(BulkOperationStore::class),
            executor: $app->make(TransferBulkExecutor::class),
            results: $app->make(BulkResultReader::class),
            failures: $app->make(BulkFailureReader::class),
            clock: $app->make(ClockInterface::class),
        ));
    }

    public static function boot(Application $app): void
    {
        if ($app->make(BulkOptions::class)->enabled) {
            BulkRouteRegistrar::register($app);
        }
    }

    private static function factory(Application $app): ?BulkTransferFactory
    {
        return $app->bound(BulkTransferFactory::class)
            ? $app->make(BulkTransferFactory::class)
            : null;
    }

    private static function session(Application $app, BulkOperation $operation): BulkTransferSession
    {
        $factory = self::factory($app);

        if ($factory === null) {
            throw new TransferCapabilityUnavailable;
        }

        return $factory->create($operation);
    }
}
