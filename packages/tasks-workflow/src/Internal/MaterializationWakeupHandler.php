<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Exceptions\StaleMaterialization;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Contracts\DueWakeupHandler;
use CoreX\Wakeup\Data\WakeupDelivery;
use Illuminate\Contracts\Container\Container;

final class MaterializationWakeupHandler implements DueWakeupHandler
{
    public function __construct(
        private readonly Container $container,
        private readonly TenantContextResolver $contexts,
    ) {}

    public function handle(WakeupDelivery $delivery): void
    {
        $decoded = MaterializationHasher::decodeWakeupPayload($delivery->request->payload);
        $series = $decoded['series'];
        $window = $decoded['window'];
        $connection = app('db')->connection();
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

        $this->container->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
        $this->container->forgetScopedInstances();

        try {
            $this->container->make(TaskRecurrenceCommand::class)->materialize(
                scope: $scope,
                series: $series,
                window: $window,
                token: new WriteToken(
                    operationKey: 'wakeup:'.$delivery->request->requestKey,
                    payloadHash: MaterializationHasher::payload($scope, $series, $window),
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
