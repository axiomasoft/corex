<?php

declare(strict_types=1);

namespace CoreX\Workflow\Contracts;

use CoreX\Workflow\Data\CaptureResult;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WorkflowBinding
{
    public function capture(TriggerRequest $request): CaptureResult;

    public function signal(SignalRequest $request): SignalResult;

    public function fire(TransitionRequest $request): TransitionResult;
}
