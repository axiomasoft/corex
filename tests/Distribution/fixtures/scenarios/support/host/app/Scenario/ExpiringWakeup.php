<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use Illuminate\Support\Facades\DB;

final readonly class ExpiringWakeup implements DueWakeupHandler
{
    public function __construct(private CurrentWorkScope $current, private ScenarioClock $clock) {}

    public function handle(WakeupDelivery $delivery): void
    {
        $database = DB::connection();
        $scope = $delivery->request->scope;
        $this->current->assert($scope, $database);
        $database->table('scenario_effects')->insert([
            'reference' => $delivery->request->requestKey, 'marker' => $scope->principalId.'|'.$scope->workspaceId,
        ]);

        if ($delivery->request->requestKey === 'expired-cas' && $delivery->attempt === 1) {
            Scenario::barrier('before-expired-cas', [
                'connection' => Scenario::describe($database), 'transaction_level' => $database->transactionLevel(),
                'principal' => $scope->principalId, 'workspace' => $scope->workspaceId,
            ]);
            $this->clock->advance(10);
        }
    }
}
