<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\EngineEffectRef;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\StartOutcome;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use Throwable;

#[Internal]
final readonly class StartIntentWorker
{
    public function __construct(
        private Application $app,
        private DatabaseManager $database,
        private TenantContextResolver $contexts,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $current,
        private StoredWorkRunner $runner,
        private WorkScopeRunner $work,
        private DataScopeCodec $codec,
        private WorkflowIntentStore $intents,
        private ProviderEffectLedger $effects,
        private ScopeAdmission $admission,
        private ExceptionHandler $exceptions,
        private ClockInterface $clock,
    ) {}

    public function sweep(int $limit): int
    {
        $owner = $this->scopes->forCurrentPrincipal($this->contexts->current());
        $database = $this->database->connection();
        $this->current->assert($owner, $database);

        if ($database->transactionLevel() !== 0) {
            throw new LogicException('Workflow start sweep must run outside owner transactions.');
        }

        $claims = $this->work->run($owner, fn (): array => $this->intents->claim($database, $owner, $limit));
        foreach ($claims as $row) {
            $this->run($row, $database, $owner);
        }

        return count($claims);
    }

    private function run(object $row, Connection $outer, DataScope $owner): void
    {
        $id = Value::text(Value::field($row, 'id'));
        $digest = Value::text(Value::field($row, 'payload_hash'));
        $effectKey = Value::text(Value::field($row, 'effect_key'));
        $state = Value::text(Value::field($row, 'state'));
        $prior = Value::text(Value::field($row, 'prior_state'));

        try {
            $scope = $this->codec->decode(Value::map(json_decode(Value::text(Value::field($row, 'scope_json')), true, flags: JSON_THROW_ON_ERROR)));
            $proof = new StoredWorkProof(WorkflowIntentStore::OWNER, WorkflowIntentStore::proofReference($id, $digest, $effectKey), Value::text(Value::field($row, 'scope_seal')));
            $this->runner->runStored($scope, $proof, function (TenantContext $context) use ($scope, $row, $state, $prior, $digest, $effectKey, $id): void {
                $db = $this->database->connection();
                $this->current->assert($scope, $db);
                $namespace = Value::text(Value::field($row, 'provider_namespace'));
                $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, WorkflowIntentStore::OWNER, $namespace, $effectKey);
                $status = $this->effects->inspect($effect);

                if ($prior === 'sending' || $prior === 'claimed') {
                    $this->recover($db, $scope, $row, $state, $status);

                    return;
                }

                $request = $this->request($context, $scope, $row, $digest, $effectKey);
                $engine = $this->app->make(EngineAdapter::class);
                $provider = $engine->provider($request->definition());

                if ($prior === 'accepted' || $prior === 'unknown') {
                    $this->reconcile($db, $scope, $row, $state, $effect, $engine, $provider);

                    return;
                }

                if ($provider === null || $provider->namespace !== $namespace) {
                    throw new UnsupportedWorkflowCapability('The captured workflow definition and provider are unavailable.');
                }

                if (Value::integer(Value::field($row, 'attempt')) > 5) {
                    $this->intents->compareAndSet($db, $scope, $row, $state, ['state' => 'failed', 'lease_until' => null]);

                    return;
                }

                $ticket = $this->effects->reserve($scope, new ProviderAttempt(
                    $effect, $id, Value::text(Value::field($row, 'claim_token')), Value::integer(Value::field($row, 'attempt')),
                    new DateTimeImmutable(Value::text(Value::field($row, 'lease_until')), new DateTimeZone('UTC')), $digest,
                    singleInstanceReference: true,
                ));

                if (! $this->intents->compareAndSet($db, $scope, $row, 'claimed', ['state' => 'sending', 'provider_ticket' => $ticket->id])) {
                    return;
                }

                try {
                    $outcome = $engine->start($request);
                } catch (Throwable $exception) {
                    $this->exceptions->report($exception);
                    $outcome = new StartResult(StartOutcome::Unknown);
                }
                $this->recordResult($db, $scope, $row, 'sending', $effect, $ticket, $outcome);
            });
        } catch (ScopeContextMismatch|UnsupportedWorkflowCapability|InvalidArgumentException|JsonException $exception) {
            $this->exceptions->report($exception);
            $this->intents->compareAndSet($outer, $owner, $row, $state, [
                'state' => $state === 'claimed' && $prior !== 'claimed' ? 'quarantined' : $state,
                'lease_until' => null,
                'due_at' => null,
            ]);
        }
    }

    private function recover(Connection $db, DataScope $scope, object $row, string $state, ?ProviderEffectStatus $status): void
    {
        if ($status === null || Value::field($row, 'claim_token') !== $status->claimToken) {
            $this->intents->compareAndSet($db, $scope, $row, $state, [
                'state' => $state === 'sending' ? 'unknown' : 'retry-scheduled',
                'due_at' => $state === 'sending' ? null : WorkflowIntentStore::time($this->clock->now()),
                'lease_until' => null,
            ]);

            return;
        }

        if (in_array($status->state, ['conflicting', 'isolated'], true)) {
            $this->project($db, $scope, $row, $state, $status);

            return;
        }

        $ticket = $status->ticket;

        if ($this->intents->compareAndSet($db, $scope, $row, $state, [
            'state' => 'unknown',
            'provider_ticket' => $ticket->id,
            'due_at' => WorkflowIntentStore::time($this->clock->now()),
            'lease_until' => null,
        ])) {
            if ($status->state === 'outstanding') {
                $this->admission->settle($ticket, new AttemptEvidence('unknown', $status->ownerRef));
            }
        }
    }

    private function reconcile(Connection $db, DataScope $scope, object $row, string $state, ProviderEffectRef $effect, EngineAdapter $engine, ?EngineProvider $provider): void
    {
        $status = $this->effects->inspect($effect);

        if ($status === null || $status->ticket->id !== Value::field($row, 'provider_ticket')) {
            $this->release($db, $scope, $row, $state, false);

            return;
        }

        if (in_array($status->state, ['conflicting', 'isolated'], true)) {
            $this->project($db, $scope, $row, $state, $status);

            return;
        }

        $observation = $status->observation;

        if ($observation !== null && ($observation->kind === ProviderObservationKind::Confirmed
            || $observation->kind === ProviderObservationKind::Permanent
            || $observation->kind === ProviderObservationKind::Absent
            || ($observation->kind === ProviderObservationKind::Accepted
                && ($state === 'unknown' || Value::field($row, 'provider_instance') !== $observation->reference)))) {
            $this->project($db, $scope, $row, $state, $status);

            return;
        }

        if ($provider === null || $provider->namespace !== $effect->providerNamespace || ! $provider->statusLookup) {
            $this->release($db, $scope, $row, $state, true);

            return;
        }

        $instance = Value::field($row, 'provider_instance');

        try {
            $result = $engine->reconcile(new EngineEffectRef($scope, $effect->providerNamespace, $effect->effectKey, is_string($instance) ? $instance : null));
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
            $result = new StartResult(StartOutcome::Unknown);
        }

        if ($result->status === StartOutcome::Unknown || $result->status === StartOutcome::RetryableBeforeSend
            || ($state === 'accepted' && $result->status === StartOutcome::AbsentWithProof)) {
            $this->release($db, $scope, $row, $state, true);

            return;
        }

        $this->recordResult($db, $scope, $row, $state, $effect, $status->ticket, $result);
    }

    private function release(Connection $db, DataScope $scope, object $row, string $state, bool $poll): void
    {
        $this->intents->compareAndSet($db, $scope, $row, $state, [
            'due_at' => $poll ? WorkflowIntentStore::time($this->clock->now()->modify('+15 seconds')) : null,
            'lease_until' => null,
        ]);
    }

    private function recordResult(Connection $db, DataScope $scope, object $row, string $state, ProviderEffectRef $effect, AdmissionTicket $ticket, StartResult $result): void
    {
        $kind = match ($result->status) {
            StartOutcome::Accepted => ProviderObservationKind::Accepted,
            StartOutcome::Confirmed => ProviderObservationKind::Confirmed,
            StartOutcome::RetryableBeforeSend, StartOutcome::AbsentWithProof => ProviderObservationKind::Absent,
            StartOutcome::Permanent => ProviderObservationKind::Permanent,
            StartOutcome::Unknown => ProviderObservationKind::Unknown,
        };
        $this->effects->observe($ticket, new ProviderObservation(
            $kind, $result->instanceRef ?? $result->errorCode ?? $result->status->value, $effect->providerNamespace,
        ));

        $status = $this->effects->inspect($effect);

        if ($status?->ticket->id !== $ticket->id) {
            $this->release($db, $scope, $row, $state, false);

            return;
        }

        $this->project($db, $scope, $row, $state, $status);
    }

    private function project(Connection $db, DataScope $scope, object $row, string $state, ProviderEffectStatus $status): void
    {
        if (in_array($status->state, ['conflicting', 'isolated'], true)) {
            $this->intents->compareAndSet($db, $scope, $row, $state, WorkflowIntentStore::quarantineChanges($status->firstProviderRef));

            return;
        }

        $observation = $status->observation;

        if ($observation === null) {
            $this->release($db, $scope, $row, $state, true);

            return;
        }

        $ticket = $status->ticket;

        if (WorkflowIntentStore::instanceConflict($row, $observation, $status->firstProviderRef)) {
            if ($this->intents->compareAndSet($db, $scope, $row, $state, WorkflowIntentStore::quarantineChanges($status->firstProviderRef))
                && $status->state === 'outstanding') {
                $this->admission->settle($ticket, new AttemptEvidence('unknown', $status->ownerRef));
            }

            return;
        }

        $next = match ($observation->kind) {
            ProviderObservationKind::Accepted => 'accepted',
            ProviderObservationKind::Confirmed => 'confirmed',
            ProviderObservationKind::Absent => 'retry-scheduled',
            ProviderObservationKind::Permanent => 'failed',
            ProviderObservationKind::Unknown => 'unknown',
        };
        $retry = in_array($next, ['accepted', 'retry-scheduled', 'unknown'], true);
        $instance = in_array($next, ['accepted', 'confirmed'], true) ? $observation->reference : Value::field($row, 'provider_instance');

        if ($this->intents->compareAndSet($db, $scope, $row, $state, [
            'state' => $next,
            'provider_instance' => $instance,
            'provider_receipt' => $observation->reference,
            'lease_until' => null,
            'due_at' => $retry ? WorkflowIntentStore::time($this->clock->now()->modify('+15 seconds')) : null,
        ])) {
            // The external obligation is released only after the owner projection commits.
            if ($next !== 'accepted') {
                $this->admission->settle($ticket, new AttemptEvidence($next === 'unknown' ? 'unknown' : 'reconciled', Value::text(Value::field($row, 'id'))));
            }
        }
    }

    public function request(TenantContext $context, DataScope $scope, object $row, string $digest, string $effectKey): TriggerRequest
    {
        $payload = Value::map(json_decode(Value::text(Value::field($row, 'payload')), true, flags: JSON_THROW_ON_ERROR));
        $request = new TriggerRequest(
            context: $context,
            scope: $scope,
            module: Value::text(Value::field($row, 'module')),
            entityHandle: Value::text($payload['entity_handle'] ?? null),
            eventId: Value::text($payload['event_id'] ?? null),
            eventName: Value::text($payload['event_name'] ?? null),
            subjectRef: Value::text(Value::field($row, 'subject_ref')),
            definitionRef: Value::text(Value::field($row, 'definition_ref')),
            versionRef: Value::text(Value::field($row, 'version_ref')),
            scalarSnapshot: JsonDocuments::capture($payload['scalar_snapshot'] ?? null),
            requestKey: Value::text(Value::field($row, 'request_key')),
            effectKey: $effectKey,
        );
        $stamp = $this->app->make(SqlWorkflowBinding::class)->definitionStamp($request);

        if (! WorkflowDefinitionStamp::matches($payload, $stamp)
            || $request->definitionRef !== ($payload['definition_ref'] ?? null)
            || $request->subjectRef !== ($payload['subject_ref'] ?? null)
            || Value::field($row, 'provider_namespace') !== ($payload['provider_namespace'] ?? null)
            || ! hash_equals($digest, WorkflowDefinitionStamp::payloadHash('trigger', $request->requestKey, WorkflowDefinitionStamp::canonicalPayload($payload)))) {
            throw new UnsupportedWorkflowCapability('The captured workflow intent failed its pinned definition check.');
        }

        return $request;
    }
}
