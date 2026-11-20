<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Data\Data\WriteToken;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MaterializationWakeupHandler implements DueWakeupHandler
{
    public function __construct(private TaskRecurrenceCommand $command) {}

    public function handle(WakeupDelivery $delivery): void
    {
        $scope = $delivery->request->scope;
        $decoded = MaterializationHasher::decodeWakeupPayload(JsonDocuments::map($delivery->request->payload));
        $series = $decoded['series'];
        $window = $decoded['window'];

        $this->command->materialize(
            scope: $scope,
            series: $series,
            window: $window,
            token: new WriteToken(
                operationKey: $delivery->idempotencyKey,
                payloadHash: MaterializationHasher::payload(scope: $scope, series: $series, window: $window),
                expectedRecordVersion: $series->immutableRevision,
            ),
        );
    }
}
