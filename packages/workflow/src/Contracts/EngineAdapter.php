<?php

declare(strict_types=1);

namespace CoreX\Workflow\Contracts;

use CoreX\Workflow\Data\DefinitionRequest;
use CoreX\Workflow\Data\EngineEffectRef;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface EngineAdapter
{
    /** Resolve the exact pinned definition for this context and trigger without external I/O. Return null if unavailable. */
    public function provider(DefinitionRequest $request): ?EngineProvider;

    public function reconcile(EngineEffectRef $effect): StartResult;

    public function start(TriggerRequest $request): StartResult;

    public function signal(SignalRequest $request): SignalResult;

    public function transition(TransitionRequest $request): TransitionResult;
}
