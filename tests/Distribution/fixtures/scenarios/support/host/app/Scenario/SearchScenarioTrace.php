<?php

declare(strict_types=1);

namespace App\Scenario;

final class SearchScenarioTrace
{
    public array $backend = [];

    public array $loads = [];

    public array $submissions = [];

    private bool $paused = false;

    public function pauseProjection(string $recordId, array $values): void
    {
        if (! $this->paused && getenv('COREX_SCENARIO_PROJECTION_BARRIER') === '1') {
            $this->paused = true;
            Scenario::barrier('rights-projected', ['id' => $recordId, 'values' => $values]);
        }
    }

    public function reset(): void
    {
        $this->backend = [];
        $this->loads = [];
        $this->submissions = [];
    }
}
