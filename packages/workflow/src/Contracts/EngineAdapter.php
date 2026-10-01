<?php

declare(strict_types=1);

namespace CoreX\Workflow\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface EngineAdapter
{
    public function start(TriggerRequest $request): StartResult;

    public function signal(SignalRequest $request): SignalResult;

    public function fire(TransitionRequest $request): TransitionResult;
}
