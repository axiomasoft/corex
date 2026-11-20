<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use Illuminate\Support\Facades\DB;

final class RootSweepEffect implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        $connection = DB::connection();
        $scope = $delivery->request->scope;
        app(CurrentWorkScope::class)->assert($scope, $connection);
        $principal = app(WorkPrincipalAuthority::class)->current($delivery->context);

        $connection->table('scenario_effects')->insert([
            'reference' => $delivery->request->requestKey,
            'marker' => $principal->kind.'|'.$principal->id.'|'.$delivery->context->workspace?->id.'|'.Scenario::describe($connection)['database'],
        ]);
    }
}
