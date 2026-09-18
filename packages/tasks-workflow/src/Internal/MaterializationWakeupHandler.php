<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Data\WakeupDelivery;
use Illuminate\Contracts\Container\Container;

final class MaterializationWakeupHandler implements WakeupHandler
{
    public function __construct(
        private readonly Container $container,
        private readonly TenantContextResolver $contexts,
    ) {}

    public function handle(WakeupDelivery $delivery): void
    {
        $payload = $delivery->request->payload;
        $seriesId = (string) ($payload['seriesId'] ?? '');
        $revision = (string) ($payload['immutableRevision'] ?? '');
        $definitionHash = (string) ($payload['definitionHash'] ?? '');

        if ($seriesId === '' || $revision === '' || $definitionHash === '') {
            return;
        }

        $connection = app('db')->connection();
        $scope = new DataScope(
            context: $this->contexts->current(),
            connectionIdentity: ScopeConnectionGuard::identity($connection),
            actorId: 'wakeup:'.$delivery->request->requestKey,
            restoreEpoch: str_repeat('0', 64),
        );

        $previousAuthorizer = $this->container->bound(DataScopeAuthorizer::class)
            ? $this->container->make(DataScopeAuthorizer::class)
            : null;

        $this->container->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
        $this->container->forgetScopedInstances();

        try {
            $this->container->make(TaskRecurrenceCommand::class)->materialize(
                scope: $scope,
                series: new SeriesReference(id: $seriesId, immutableRevision: $revision, definitionHash: $definitionHash),
                window: new MaterializationWindow(
                    start: (string) ($payload['start'] ?? date('Y-m-d')),
                    end: (string) ($payload['end'] ?? date('Y-m-d', strtotime('+30 days'))),
                ),
                token: new WriteToken(
                    operationKey: 'wakeup:'.$delivery->request->requestKey,
                    payloadHash: hash('sha256', 'wakeup-materialize/1'."\0".$seriesId."\0".$revision),
                    expectedRecordVersion: $revision,
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
