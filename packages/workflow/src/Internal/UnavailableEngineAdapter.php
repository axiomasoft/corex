<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;

final class UnavailableEngineAdapter implements EngineAdapter
{
    public function start(TriggerRequest $request): StartResult
    {
        throw new UnsupportedWorkflowCapability('No independently-owned workflow engine adapter is installed.');
    }

    public function signal(SignalRequest $request): SignalResult
    {
        throw new UnsupportedWorkflowCapability('No independently-owned workflow engine adapter is installed.');
    }

    public function fire(TransitionRequest $request): TransitionResult
    {
        throw new UnsupportedWorkflowCapability('No independently-owned workflow engine adapter is installed.');
    }
}
