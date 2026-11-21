<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use App\Scenario\FixtureWorkflowEngine;
use App\Scenario\Scenario;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\DefinitionRequest;
use CoreX\Workflow\Data\EngineEffectRef;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;

final class TaskLifetimeWorkflowEngine implements EngineAdapter
{
    /** @var list<array{string, ?string, string, ?string, string, string, string, string, string}> */
    public array $lookups = [];

    private readonly string $account;

    private readonly ?string $workspace;

    public function __construct(TenantContextResolver $contexts, WorkPrincipalAuthority $principals, private readonly FixtureWorkflowEngine $engine)
    {
        $context = $contexts->current();
        $principals->current($context);
        $this->account = $context->id();
        $this->workspace = $context->workspace?->id;
    }

    public function provider(DefinitionRequest $request): ?EngineProvider
    {
        $this->lookups[] = [$this->account, $this->workspace, $request->context->id(), $request->context->workspace?->id,
            $request->module, $request->entityHandle, $request->eventName, $request->definitionRef, $request->versionRef];

        return $this->account === $request->context->id() && $this->workspace === Scenario::W1 && $this->workspace === $request->context->workspace?->id
            ? $this->engine->provider($request)
            : null;
    }

    public function start(TriggerRequest $request): StartResult
    {
        return $this->engine->start($request);
    }

    public function reconcile(EngineEffectRef $effect): StartResult
    {
        return $this->engine->reconcile($effect);
    }

    public function signal(SignalRequest $request): SignalResult
    {
        return $this->engine->signal($request);
    }

    public function transition(TransitionRequest $request): TransitionResult
    {
        return $this->engine->transition($request);
    }
}
