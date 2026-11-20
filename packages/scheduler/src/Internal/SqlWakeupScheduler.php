<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Scheduling\Contracts\WakeupScheduleHint;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\CancelResult;
use CoreX\Scheduling\Data\ScheduleResult;
use CoreX\Scheduling\Data\WakeupIdentity;
use CoreX\Scheduling\Data\WakeupRequest;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Internal]
final readonly class SqlWakeupScheduler implements WakeupScheduler
{
    /** @param list<WakeupScheduleHint> $hints */
    public function __construct(
        private DatabaseManager $databases,
        private CurrentWorkScope $current,
        private WorkScopeRunner $runner,
        private StoredWorkIssuer $issuer,
        private WakeupInput $input,
        private WakeupHandlerRegistry $handlers,
        private array $hints,
        private ClockInterface $clock,
    ) {}

    public function schedule(WakeupRequest $request): ScheduleResult
    {
        $this->current->assert($request->scope);

        return $this->runner->run($request->scope, function (TenantContext $context) use ($request): ScheduleResult {
            $db = $this->databases->connection();
            $this->current->assert($request->scope, $db);

            return $db->transaction(fn (): ScheduleResult => $this->store($db, $context, $request));
        });
    }

    private function store(Connection $db, TenantContext $context, WakeupRequest $request): ScheduleResult
    {
        $definition = $this->handlers->definition($request->kind);
        $data = WakeupInput::payload($request->payload);
        $digest = $this->input->digest($request, $data);
        $identity = WakeupInput::identity($db, $request->scope, $request->kind, $request->requestKey);

        $existing = $identity->first(['id', 'request_digest']);

        if ($existing !== null) {
            return $this->replay($existing, $request, $digest);
        }

        if ($definition === null || $definition->version !== $request->handlerVersion) {
            throw new RuntimeException("Wakeup handler [{$request->kind}] at version [{$request->handlerVersion}] is not registered.");
        }
        $id = (string) Str::uuid7();
        $now = WakeupInput::time($this->clock->now());
        $row = [
            'id' => $id,
            'account_id' => $request->scope->accountId,
            'account_incarnation' => $request->scope->accountIncarnation,
            'workspace_scope_key' => WakeupInput::workspace($request->scope),
            'data_scope' => json_encode($this->input->scope($request->scope), JSON_THROW_ON_ERROR),
            'scope_seal' => $this->issuer->issue($request->scope, WakeupInput::PROOF_OWNER, WakeupInput::proofReference($id, $digest))->seal,
            'kind' => $request->kind,
            'request_key' => $request->requestKey,
            'request_digest' => $digest,
            'fire_at' => WakeupInput::time($request->fireAt),
            'window_start' => $request->window === null ? null : WakeupInput::time($request->window->start),
            'window_end' => $request->window === null ? null : WakeupInput::time($request->window->end),
            'period' => $request->window?->period,
            'payload' => json_encode($this->handlers->envelope($definition, $data), JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'generation' => 0,
            'attempt' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $inserted = $db->table('sys_wakeup_requests')->insertOrIgnoreReturning(
            $row, ['id'], ['account_id', 'account_incarnation', 'workspace_scope_key', 'kind', 'request_key'],
        );

        if ($inserted->isEmpty()) {
            return $this->replay($identity->firstOrFail(['id', 'request_digest']), $request, $digest);
        }

        $db->afterCommit(fn () => $this->dispatchHints($context, $request->fireAt));

        return new ScheduleResult('created', $request->requestKey, $id);
    }

    private function replay(object $existing, WakeupRequest $request, string $digest): ScheduleResult
    {
        if (! hash_equals(Value::text(Value::field($existing, 'request_digest')), $digest)) {
            return new ScheduleResult('conflict', $request->requestKey);
        }

        return new ScheduleResult('existing', $request->requestKey, Value::text(Value::field($existing, 'id')));
    }

    public function cancel(DataScope $scope, WakeupIdentity $identity): CancelResult
    {
        $this->current->assert($scope);

        return $this->runner->run($scope, function () use ($scope, $identity): CancelResult {
            $db = $this->databases->connection();
            $this->current->assert($scope, $db);

            return $db->transaction(function () use ($db, $scope, $identity): CancelResult {
                $query = WakeupInput::identity($db, $scope, $identity->kind, $identity->requestKey);
                $row = $query->lockForUpdate()->first();

                if ($row === null) {
                    return new CancelResult('not-found');
                }
                $stored = Value::map(json_decode(Value::text($row->data_scope), true, flags: JSON_THROW_ON_ERROR));
                ksort($stored, SORT_STRING);

                if ($this->input->scope($scope) !== $stored) {
                    return new CancelResult('not-found');
                }
                $status = match ($row->status) {
                    'pending', 'retry-scheduled' => 'cancelled',
                    'running', 'cancel-requested' => 'cancel-requested',
                    default => 'already-'.Value::text($row->status),
                };

                if ($status === 'cancelled' || $status === 'cancel-requested') {
                    $query->update(['status' => $status, 'updated_at' => WakeupInput::time($this->clock->now())]);
                }

                return new CancelResult($status);
            });
        });
    }

    private function dispatchHints(TenantContext $context, DateTimeImmutable $fireAt): void
    {
        foreach ($this->hints as $hint) {
            $hint->scheduled(context: $context, fireAt: $fireAt);
        }
    }
}
