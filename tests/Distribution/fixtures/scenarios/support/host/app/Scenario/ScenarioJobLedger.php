<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Search\Contracts\SearchProvider;

/** Process-local observations of the real queue worker and deliberately retained driver. */
final class ScenarioJobLedger
{
    public array $queries = [];

    public array $failures = [];

    public ?SearchProvider $retained = null;

    public ?SearchProvider $retainedB = null;
}
