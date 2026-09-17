<?php

declare(strict_types=1);

namespace CoreX\Workflow\Contracts;

use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;

interface EngineAdapter
{
    public function start(TriggerRequest $request): StartResult;

    public function signal(SignalRequest $request): SignalResult;

    public function fire(TransitionRequest $request): TransitionResult;
}
