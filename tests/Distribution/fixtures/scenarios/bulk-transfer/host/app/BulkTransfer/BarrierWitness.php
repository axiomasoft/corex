<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;

final class BarrierWitness
{
    public static function beforeKill(string $name, string $kind, array $evidence): void
    {
        $test = new Scenario;
        $connection = Runtime::tenant();
        $bulk = Runtime::bulk($kind);
        $checkpoint = Runtime::snapshot($bulk);

        $test->check($connection->getDriverName() === 'pgsql', 'Crash barrier requires the real installed PostgreSQL connection.');
        $test->check($bulk->lease_owner !== null && (int) $bulk->attempt >= 1, 'Crash barrier requires a live Bulk claim.');
        $test->same([], $checkpoint['checkpoint'], 'Bulk checkpoint had already advanced at the interruption point.');
        $test->check($bulk->status !== 'completed', 'Crash barrier requires an unacknowledged Bulk step.');
        $evidence += ['operation' => $bulk->id, 'tenant_session' => Scenario::describe($connection)['session'], 'local_tickets' => CrashRecovery::workerTickets()];
        $test->check($evidence['local_tickets'] !== [], 'Independent control owner has no outstanding local admission for this worker.');
        $test->report($connection, ['operation' => $bulk->id], $checkpoint, ['barrier' => $name, ...$evidence]);
        Scenario::barrier($name, $evidence);
    }
}
