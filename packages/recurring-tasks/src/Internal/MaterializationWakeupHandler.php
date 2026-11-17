<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Data\Laravel\SystemDataScopeAuthorization;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\RecurringTasks\Exceptions\StaleMaterialization;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;

final readonly class MaterializationWakeupHandler implements DueWakeupHandler
{
    public function __construct(
        private Container $container,
        private TenantContextResolver $contexts,
        private ConnectionResolverInterface $connections,
    ) {}

    public function handle(WakeupDelivery $delivery): void
    {
        $decoded = MaterializationHasher::decodeWakeupPayload(JsonDocuments::map($delivery->request->payload));
        $series = $decoded['series'];
        $window = $decoded['window'];
        /** @var Connection */
        $connection = $this->connections->connection();
        $restoreEpoch = $connection->table('corex_task_series_state')
            ->where('series_key', $series->id)
            ->value('restore_epoch');

        if (! is_string($restoreEpoch) || $restoreEpoch === '') {
            throw StaleMaterialization::definition($series->id);
        }

        $scope = DataScope::fromTenantContext(
            context: $this->contexts->current(),
            connectionIdentity: ScopeConnectionGuard::identity($connection),
            actorId: 'wakeup:'.$delivery->request->requestKey,
            restoreEpoch: $restoreEpoch,
        );

        $previousAuthorizer = $this->container->bound(DataScopeAuthorizer::class)
            ? $this->container->make(DataScopeAuthorizer::class)
            : null;

        $this->container->instance(DataScopeAuthorizer::class, new SystemDataScopeAuthorization);
        $this->container->forgetScopedInstances();

        try {
            $this->container->make(TaskRecurrenceCommand::class)->materialize(
                scope: $scope,
                series: $series,
                window: $window,
                token: new WriteToken(
                    operationKey: 'wakeup:'.$delivery->request->requestKey,
                    payloadHash: MaterializationHasher::payload(scope: $scope, series: $series, window: $window),
                    expectedRecordVersion: $series->immutableRevision,
                ),
            );
        } finally {
            if ($previousAuthorizer instanceof DataScopeAuthorizer) {
                $this->container->instance(DataScopeAuthorizer::class, $previousAuthorizer);
            } else {
                $this->container->forgetInstance(DataScopeAuthorizer::class);
            }

            $this->container->forgetScopedInstances();
        }
    }
}
