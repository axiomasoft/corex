<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Contracts\BulkTransferFactory;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Bulk\Data\BulkTransferSession;
use CoreX\Operations\Bulk\Internal\BulkFailureReader;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Bulk\Internal\BulkResultReader;
use CoreX\Operations\Bulk\Internal\BulkRetention;
use CoreX\Operations\Bulk\Internal\BulkWorker;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Internal\TransferBulkExecutor;
use CoreX\Operations\Bulk\Internal\TransferCapabilityUnavailable;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class BulkRegistrar
{
    public static function register(Application $app): void
    {
        // Registration below depends on enabled flags; this one-off read does not cache Options before boot.
        $options = BulkOptionsParser::fromArray((array) $app->make(Repository::class)->get('corex-operations.bulk', []));

        $app->singleton(BulkOptions::class, static fn (Application $app): BulkOptions => BulkOptionsParser::fromArray(
            (array) $app->make(Repository::class)->get('corex-operations.bulk', []),
        ));
        $app->singleton(BulkRetention::class, static fn (Application $app): BulkRetention => new BulkRetention(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            scopeCodec: $app->make(DataScopeCodec::class),
            contexts: $app->make(TenantContextRestorer::class),
            clock: $app->make(ClockInterface::class),
            resultRetentionSeconds: $app->make(BulkOptions::class)->resultRetentionSeconds,
            metadataRetentionSeconds: $app->make(BulkOptions::class)->metadataRetentionSeconds,
            idempotencyRetentionSeconds: $app->make(BulkOptions::class)->idempotencyRetentionSeconds,
        ));

        if (! $options->enabled) {
            return;
        }

        $app->singleton(BulkOperationStore::class, static fn (Application $app): SqlBulkOperationStore => new SqlBulkOperationStore(
            connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
            scopeCodec: $app->make(DataScopeCodec::class),
            contexts: $app->make(TenantContextRestorer::class),
            clock: $app->make(ClockInterface::class),
            leaseSeconds: $app->make(BulkOptions::class)->leaseSeconds,
            resultRetentionSeconds: $app->make(BulkOptions::class)->resultRetentionSeconds,
        ));
        $app->singleton(TransferBulkExecutor::class, static fn (Application $app): TransferBulkExecutor => new TransferBulkExecutor(
            factory: self::factory($app),
            store: $app->make(BulkOperationStore::class),
            clock: $app->make(ClockInterface::class),
            transferInstalled: interface_exists('CoreX\\Transfer\\Contracts\\TransferRunner'),
            retrySeconds: $app->make(BulkOptions::class)->retrySeconds,
            scopes: $app->make(DataScopeCodec::class),
        ));
        $app->singleton(BulkWorker::class, static fn (Application $app): BulkWorker => new BulkWorker(
            store: $app->make(BulkOperationStore::class),
            executor: $app->make(TransferBulkExecutor::class),
            clock: $app->make(ClockInterface::class),
            worker: $app->make(BulkOptions::class)->worker,
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
