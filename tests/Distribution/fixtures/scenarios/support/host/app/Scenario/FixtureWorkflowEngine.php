<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\EngineEffectRef;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\SignalRequest;
use CoreX\Workflow\Data\SignalResult;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TransitionRequest;
use CoreX\Workflow\Data\TransitionResult;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\StartOutcome;
use RuntimeException;

final readonly class FixtureWorkflowEngine implements EngineAdapter
{
    public const string NAMESPACE = 'scenario.workflow';

    public function __construct(private ProviderSimulator $provider) {}

    public function provider(TriggerRequest $request): ?EngineProvider
    {
        return $request->definitionRef === 'fixture:workflow' && $request->versionRef === 'fixture:version1'
            ? new EngineProvider(self::NAMESPACE, statusLookup: true, idempotentResend: true)
            : null;
    }

    public function start(TriggerRequest $request): StartResult
    {
        $key = $request->effectKey ?? throw new RuntimeException('Workflow start did not supply its persisted effect key.');
        $reply = $this->provider->send('workflow', $key, $request->scalarSnapshot->canonicalJson);

        return $reply['status'] === 200 ? new StartResult(StartOutcome::Confirmed, $reply['reference']) : new StartResult(StartOutcome::Unknown);
    }

    public function reconcile(EngineEffectRef $effect): StartResult
    {
        $reply = $this->provider->lookup('workflow', $effect->effectKey);

        return $reply['status'] === 200 ? new StartResult(StartOutcome::Confirmed, $reply['reference']) : new StartResult(StartOutcome::Unknown);
    }

    public function signal(SignalRequest $request): SignalResult
    {
        throw new RuntimeException('The installed scenario only admits start effects.');
    }

    public function fire(TransitionRequest $request): TransitionResult
    {
        throw new RuntimeException('The installed scenario only admits start effects.');
    }
}
