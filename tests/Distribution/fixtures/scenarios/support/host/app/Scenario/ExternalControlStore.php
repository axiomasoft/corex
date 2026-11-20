<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Database\ConnectionInterface;

final class ExternalControlStore implements ControlStoreIsolation
{
    public function verify(ConnectionInterface $control, string $deploymentId): void
    {
        $db = Scenario::describe($control)['database'];

        if ($deploymentId !== Scenario::DEPLOYMENT || $db !== getenv('COREX_SCENARIO_DB_CONTROL') || in_array($db, [
            getenv('COREX_SCENARIO_DB_A'), getenv('COREX_SCENARIO_DB_B'), getenv('COREX_SCENARIO_DB_BACKUP'),
        ], true)) {
            throw new ScopeContextMismatch('The scope control store belongs to the tenant backup topology.');
        }
    }
}
