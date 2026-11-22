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
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Laravel\ReferenceWrites;
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
        $this->current->assert(scope: $owner, connection: $database);

        if ($database->transactionLevel() !== 0) {
            throw new LogicException('Workflow start sweep must run outside owner transactions.');
        }

        $claims = $this->work->run(scope: $owner, work: fn (): array => $this->intents->claim(db: $database, scope: $owner, limit: $limit));
        foreach ($claims as $row) {
            $this->run(row: $row, outer: $database, owner: $owner);
        }

        return count($claims);
    }

    private function run(object $row, Connection $outer, DataScope $owner): void
    {
        $id = Value::text(Value::field(row: $row, name: 'id'));
        $digest = Value::text(Value::field(row: $row, name: 'payload_hash'));
        $effectKey = Value::text(Value::field(row: $row, name: 'effect_key'));
        $state = Value::text(Value::field(row: $row, name: 'state'));
        $prior = Value::text(Value::field(row: $row, name: 'prior_state'));

        try {
            $scope = $this->codec->decode(Value::map(json_decode(Value::text(Value::field(row: $row, name: 'scope_json')), true, flags: JSON_THROW_ON_ERROR)));
            $proof = new StoredWorkProof(owner: WorkflowIntentStore::OWNER, reference: WorkflowIntentStore::proofReference(id: $id, digest: $digest, effectKey: $effectKey), seal: Value::text(Value::field(row: $row, name: 'scope_seal')));
            $this->runner->runStored(scope: $scope, proof: $proof, work: function (TenantContext $context) use ($scope, $row, $state, $prior, $digest, $effectKey, $id): void {
                $db = $this->database->connection();
                $this->current->assert(scope: $scope, connection: $db);
                $namespace = Value::text(Value::field(row: $row, name: 'provider_namespace'));
                $effect = new ProviderEffectRef(accountId: $scope->accountId, accountIncarnation: $scope->accountIncarnation, workspaceId: $scope->workspaceId, owner: WorkflowIntentStore::OWNER, providerNamespace: $namespace, effectKey: $effectKey);
                $status = $this->effects->inspect($effect);

                if ($prior === 'sending' || $prior === 'claimed') {
                    $this->recover(db: $db, scope: $scope, row: $row, state: $state, status: $status);

                    return;
                }

                $request = $this->request(context: $context, scope: $scope, row: $row, digest: $digest, effectKey: $effectKey);
                $engine = $this->app->make(EngineAdapter::class);
                $provider = $engine->provider($request->definition());

                if ($prior === 'accepted' || $prior === 'unknown') {
                    $this->reconcile(db: $db, scope: $scope, row: $row, state: $state, effect: $effect, engine: $engine, provider: $provider);

                    return;
                }

                if ($provider === null || $provider->namespace !== $namespace) {
                    throw new UnsupportedWorkflowCapability('The captured workflow definition and provider are unavailable.');
                }

                if (Value::integer(Value::field(row: $row, name: 'attempt')) > 5) {
                    $this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: ['state' => 'failed', 'lease_until' => null]);

                    return;
                }

                $ticket = ReferenceWrites::reserveEffect(scope: $scope, attempt: new ProviderAttempt(
                    effect: $effect, ownerRef: $id, claimToken: Value::text(Value::field(row: $row, name: 'claim_token')), attempt: Value::integer(Value::field(row: $row, name: 'attempt')),
                    deadline: new DateTimeImmutable(Value::text(Value::field(row: $row, name: 'lease_until')), new DateTimeZone('UTC')), digest: $digest,
                    singleInstanceReference: true,
                ), producer: 'axiomasoft/corex-workflow', ledger: $this->effects);

                if (! $this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: 'claimed', changes: ['state' => 'sending', 'provider_ticket' => $ticket->id])) {
                    return;
                }

                try {
                    $outcome = $engine->start($request);
                } catch (Throwable $exception) {
                    $this->exceptions->report($exception);
                    $outcome = new StartResult(StartOutcome::Unknown);
                }
                $this->recordResult(db: $db, scope: $scope, row: $row, state: 'sending', effect: $effect, ticket: $ticket, result: $outcome);
            });
        } catch (ScopeContextMismatch|ScopeStale|UnsupportedWorkflowCapability|InvalidArgumentException|JsonException $exception) {
            $this->exceptions->report($exception);
            $this->intents->compareAndSet(db: $outer, scope: $owner, row: $row, expectedState: $state, changes: [
                'state' => $state === 'claimed' && $prior !== 'claimed' ? 'quarantined' : $state,
                'lease_until' => null,
                'due_at' => null,
            ]);
        }
    }

    private function recover(Connection $db, DataScope $scope, object $row, string $state, ?ProviderEffectStatus $status): void
    {
        if ($status === null || Value::field(row: $row, name: 'claim_token') !== $status->claimToken) {
            $this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: [
                'state' => $state === 'sending' ? 'unknown' : 'retry-scheduled',
                'due_at' => $state === 'sending' ? null : WorkflowIntentStore::time($this->clock->now()),
                'lease_until' => null,
            ]);

            return;
        }

        if (in_array($status->state, ['conflicting', 'isolated'], true)) {
            $this->project(db: $db, scope: $scope, row: $row, state: $state, status: $status);

            return;
        }

        $ticket = $status->ticket;

        if ($this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: [
            'state' => 'unknown',
            'provider_ticket' => $ticket->id,
            'due_at' => WorkflowIntentStore::time($this->clock->now()),
            'lease_until' => null,
        ])) {
            if ($status->state === 'outstanding') {
                $this->admission->settle(ticket: $ticket, evidence: new AttemptEvidence(outcome: 'unknown', reference: $status->ownerRef));
            }
        }
    }

    private function reconcile(Connection $db, DataScope $scope, object $row, string $state, ProviderEffectRef $effect, EngineAdapter $engine, ?EngineProvider $provider): void
    {
        $status = $this->effects->inspect($effect);

        if ($status === null || $status->ticket->id !== Value::field(row: $row, name: 'provider_ticket')) {
            $this->release(db: $db, scope: $scope, row: $row, state: $state, poll: false);

            return;
        }

        if (in_array($status->state, ['conflicting', 'isolated'], true)) {
            $this->project(db: $db, scope: $scope, row: $row, state: $state, status: $status);

            return;
        }

        $observation = $status->observation;

        if ($observation !== null && ($observation->kind === ProviderObservationKind::Confirmed
            || $observation->kind === ProviderObservationKind::Permanent
            || $observation->kind === ProviderObservationKind::Absent
            || ($observation->kind === ProviderObservationKind::Accepted
                && ($state === 'unknown' || Value::field(row: $row, name: 'provider_instance') !== $observation->reference)))) {
            $this->project(db: $db, scope: $scope, row: $row, state: $state, status: $status);

            return;
        }

        if ($provider === null || $provider->namespace !== $effect->providerNamespace || ! $provider->statusLookup) {
            $this->release(db: $db, scope: $scope, row: $row, state: $state, poll: true);

            return;
        }

        $instance = Value::field(row: $row, name: 'provider_instance');

        try {
            $result = $engine->reconcile(new EngineEffectRef(scope: $scope, providerNamespace: $effect->providerNamespace, effectKey: $effect->effectKey, instanceRef: is_string($instance) ? $instance : null));
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
            $result = new StartResult(StartOutcome::Unknown);
        }

        if ($result->status === StartOutcome::Unknown || $result->status === StartOutcome::RetryableBeforeSend
            || ($state === 'accepted' && $result->status === StartOutcome::AbsentWithProof)) {
            $this->release(db: $db, scope: $scope, row: $row, state: $state, poll: true);

            return;
        }

        $this->recordResult(db: $db, scope: $scope, row: $row, state: $state, effect: $effect, ticket: $status->ticket, result: $result);
    }

    private function release(Connection $db, DataScope $scope, object $row, string $state, bool $poll): void
    {
        $this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: [
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
        $this->effects->observe(ticket: $ticket, observation: new ProviderObservation(
            kind: $kind, reference: $result->instanceRef ?? $result->errorCode ?? $result->status->value, observer: $effect->providerNamespace,
        ));

        $status = $this->effects->inspect($effect);

        if ($status?->ticket->id !== $ticket->id) {
            $this->release(db: $db, scope: $scope, row: $row, state: $state, poll: false);

            return;
        }

        $this->project(db: $db, scope: $scope, row: $row, state: $state, status: $status);
    }

    private function project(Connection $db, DataScope $scope, object $row, string $state, ProviderEffectStatus $status): void
    {
        if (in_array($status->state, ['conflicting', 'isolated'], true)) {
            $this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: WorkflowIntentStore::quarantineChanges($status->firstProviderRef));

            return;
        }

        $observation = $status->observation;

        if ($observation === null) {
            $this->release(db: $db, scope: $scope, row: $row, state: $state, poll: true);

            return;
        }

        $ticket = $status->ticket;

        if (WorkflowIntentStore::instanceConflict(row: $row, observation: $observation, firstProviderRef: $status->firstProviderRef)) {
            if ($this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: WorkflowIntentStore::quarantineChanges($status->firstProviderRef))
                && $status->state === 'outstanding') {
                $this->admission->settle(ticket: $ticket, evidence: new AttemptEvidence(outcome: 'unknown', reference: $status->ownerRef));
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
        $instance = in_array($next, ['accepted', 'confirmed'], true) ? $observation->reference : Value::field(row: $row, name: 'provider_instance');

        if ($this->intents->compareAndSet(db: $db, scope: $scope, row: $row, expectedState: $state, changes: [
            'state' => $next,
            'provider_instance' => $instance,
            'provider_receipt' => $observation->reference,
            'lease_until' => null,
            'due_at' => $retry ? WorkflowIntentStore::time($this->clock->now()->modify('+15 seconds')) : null,
        ])) {
            // The external obligation is released only after the owner projection commits.
            if ($next !== 'accepted') {
                $this->admission->settle(ticket: $ticket, evidence: new AttemptEvidence(outcome: $next === 'unknown' ? 'unknown' : 'reconciled', reference: Value::text(Value::field(row: $row, name: 'id'))));
            }
        }
    }

    public function request(TenantContext $context, DataScope $scope, object $row, string $digest, string $effectKey): TriggerRequest
    {
        $payload = Value::map(json_decode(Value::text(Value::field(row: $row, name: 'payload')), true, flags: JSON_THROW_ON_ERROR));
        $request = new TriggerRequest(
            context: $context,
            scope: $scope,
            module: Value::text(Value::field(row: $row, name: 'module')),
            entityHandle: Value::text($payload['entity_handle'] ?? null),
            eventId: Value::text($payload['event_id'] ?? null),
            eventName: Value::text($payload['event_name'] ?? null),
            subjectRef: Value::text(Value::field(row: $row, name: 'subject_ref')),
            definitionRef: Value::text(Value::field(row: $row, name: 'definition_ref')),
            versionRef: Value::text(Value::field(row: $row, name: 'version_ref')),
            scalarSnapshot: JsonDocuments::capture($payload['scalar_snapshot'] ?? null),
            requestKey: Value::text(Value::field(row: $row, name: 'request_key')),
            effectKey: $effectKey,
        );
        $stamp = $this->app->make(SqlWorkflowBinding::class)->definitionStamp($request);

        if (! WorkflowDefinitionStamp::matches(stored: $payload, stamp: $stamp)
            || $request->definitionRef !== ($payload['definition_ref'] ?? null)
            || $request->subjectRef !== ($payload['subject_ref'] ?? null)
            || Value::field(row: $row, name: 'provider_namespace') !== ($payload['provider_namespace'] ?? null)
            || ! hash_equals($digest, WorkflowDefinitionStamp::payloadHash(kind: 'trigger', requestKey: $request->requestKey, canonical: WorkflowDefinitionStamp::canonicalPayload($payload)))) {
            throw new UnsupportedWorkflowCapability('The captured workflow intent failed its pinned definition check.');
        }

        return $request;
    }
}
