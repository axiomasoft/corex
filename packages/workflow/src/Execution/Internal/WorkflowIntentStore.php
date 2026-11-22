<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Workflow\Data\CaptureResult;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class WorkflowIntentStore
{
    public const string OWNER = 'corex.workflow';

    public function __construct(private DataScopeCodec $codec, private StoredWorkIssuer $issuer, private ClockInterface $clock) {}

    /** @param array<string, mixed> $payload */
    public function capture(ConnectionInterface $connection, TriggerRequest $request, EngineProvider $provider, array $payload): CaptureResult
    {
        $scope = $request->scope;
        $logicalScope = hash('sha256', json_encode([
            $scope->accountId, $scope->accountIncarnation, $scope->workspaceId, $request->module,
        ], JSON_THROW_ON_ERROR));
        $digest = WorkflowDefinitionStamp::payloadHash(kind: 'trigger', requestKey: $request->requestKey, canonical: WorkflowDefinitionStamp::canonicalPayload($payload));
        $id = (string) Str::uuid7();
        $effectKey = hash('sha256', "corex.workflow.start/1\0{$logicalScope}\0{$request->requestKey}");
        $proof = $this->issuer->issue(scope: $scope, owner: self::OWNER, reference: self::proofReference(id: $id, digest: $digest, effectKey: $effectKey));
        $now = self::time($this->clock->now());
        $columns = [
            'id' => $id,
            'request_key' => $request->requestKey,
            'payload_hash' => $digest,
            'account_id' => $scope->accountId,
            'account_incarnation' => $scope->accountIncarnation,
            'workspace_id' => $scope->workspaceId,
            'workspace_scope' => self::workspace($scope),
            'deployment_id' => $scope->deploymentId,
            'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration,
            'restore_epoch' => $scope->restoreEpoch,
            'scope_json' => json_encode($this->codec->encode($scope), JSON_THROW_ON_ERROR),
            'scope_seal' => $proof->seal,
            'module' => $request->module,
            'kind' => 'trigger',
            'definition_ref' => $request->definitionRef,
            'version_ref' => $request->versionRef,
            'subject_ref' => $request->subjectRef,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            'provider_namespace' => $provider->namespace,
            'effect_key' => $effectKey,
            'state' => 'pending',
            'due_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $inserted = $connection->table('sys_workflow_intents')->insertOrIgnoreReturning(
            $columns, ['id'], ['account_id', 'account_incarnation', 'workspace_scope', 'module', 'kind', 'request_key'],
        );

        if ($inserted->isNotEmpty()) {
            return new CaptureResult(status: CaptureStatus::Created, intentRef: $id, digest: $digest);
        }

        $existing = $connection->table('sys_workflow_intents')->where('account_id', $scope->accountId)
            ->where('account_incarnation', $scope->accountIncarnation)->where('workspace_scope', self::workspace($scope))
            ->where('module', $request->module)->where('kind', 'trigger')->where('request_key', $request->requestKey)->lockForUpdate()->first();

        if ($existing === null) {
            throw new LogicException('The conflicting workflow owner row disappeared.');
        }

        if (! hash_equals(Value::text($existing->payload_hash), $digest)) {
            return new CaptureResult(CaptureStatus::Conflict);
        }

        return new CaptureResult(status: CaptureStatus::Existing, intentRef: Value::text($existing->id), digest: $digest);
    }

    public static function proofReference(string $id, string $digest, string $effectKey): string
    {
        return $id.':'.$digest.':'.$effectKey;
    }

    /** @return list<object> */
    public function claim(Connection $db, DataScope $scope, int $limit): array
    {
        $now = $this->clock->now();
        $deadline = $now->modify('+60 seconds');

        return $db->transaction(function () use ($db, $scope, $limit, $now, $deadline): array {
            $due = $this->scoped(db: $db, scope: $scope)
                ->where(static fn (Builder $query) => $query
                    ->where(static fn (Builder $pending) => $pending->whereIn('state', ['pending', 'retry-scheduled', 'accepted', 'unknown'])
                        ->where('due_at', '<=', self::time($now))
                        ->where(static fn (Builder $lease) => $lease->whereNull('lease_until')->orWhere('lease_until', '<=', self::time($now))))
                    ->orWhere(static fn (Builder $expired) => $expired->whereIn('state', ['claimed', 'sending'])
                        ->where('lease_until', '<=', self::time($now))))
                ->orderBy('due_at')->orderBy('id')->limit($limit)
                ->lock($db->getDriverName() === 'pgsql' ? 'FOR UPDATE SKIP LOCKED' : true)->get();
            $claims = [];

            foreach ($due as $row) {
                $prior = Value::text($row->state);
                $first = $prior === 'pending' || $prior === 'retry-scheduled';
                $state = $first ? 'claimed' : $prior;
                $token = $first ? (string) Str::uuid7() : Value::text($row->claim_token);
                $attempt = Value::integer($row->attempt) + (int) $first;
                $claim = (string) Str::uuid7();
                $changes = [
                    'state' => $state, 'claim_token' => $token, 'worker_claim' => $claim,
                    'lease_until' => self::time($deadline), 'attempt' => $attempt, 'updated_at' => self::time($now),
                ];
                $this->scoped(db: $db, scope: $scope)->where('id', $row->id)->update($changes);
                $claims[] = (object) array_merge((array) $row, $changes, ['prior_state' => $prior]);
            }

            return $claims;
        });
    }

    /** @param array<string, mixed> $changes */
    public function compareAndSet(Connection $db, DataScope $scope, object $row, string $expectedState, array $changes): bool
    {
        return $this->scoped(db: $db, scope: $scope)->where('id', Value::text(Value::field(row: $row, name: 'id')))
            ->where('state', $expectedState)->where('worker_claim', Value::text(Value::field(row: $row, name: 'worker_claim')))
            ->where('claim_token', Value::text(Value::field(row: $row, name: 'claim_token')))->where('lease_until', '>', self::time($this->clock->now()))
            ->update($changes + ['updated_at' => self::time($this->clock->now())]) === 1;
    }

    public static function instanceConflict(object $row, ProviderObservation $observation, ?string $firstProviderRef): bool
    {
        $instance = Value::field(row: $row, name: 'provider_instance');

        return in_array($observation->kind, [ProviderObservationKind::Accepted, ProviderObservationKind::Confirmed], true)
            && (($firstProviderRef !== null && ! hash_equals($firstProviderRef, $observation->reference))
                || (is_string($instance) && ! hash_equals($instance, $observation->reference)));
    }

    /** @return array<string, string|null> */
    public static function quarantineChanges(?string $firstProviderRef): array
    {
        return ['state' => 'quarantined', 'lease_until' => null, 'due_at' => null]
            + ($firstProviderRef === null ? [] : ['provider_instance' => $firstProviderRef, 'provider_receipt' => $firstProviderRef]);
    }

    public static function time(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public function scoped(Connection $db, DataScope $scope): Builder
    {
        $query = $db->table('sys_workflow_intents')->where('account_id', $scope->accountId)
            ->where('account_incarnation', $scope->accountIncarnation)->where('binding_generation', $scope->bindingGeneration)
            ->where('restore_epoch', $scope->restoreEpoch)->where('deployment_id', $scope->deploymentId)->where('binding_id', $scope->bindingId);

        return $scope->workspaceId === null ? $query : $query->where('workspace_id', $scope->workspaceId);
    }

    private static function workspace(DataScope $scope): string
    {
        return $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId;
    }
}
