<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Internal;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Contracts\WakeupHint;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Data\CancelResult;
use CoreX\Wakeup\Data\ScheduleResult;
use CoreX\Wakeup\Data\WakeupRequest;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

final class SqlWakeupScheduler implements WakeupScheduler
{
    /**
     * @param  array<string, WakeupHandler>  $handlers
     * @param  list<WakeupHint>  $hints
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContextResolver $contexts,
        private readonly array $handlers,
        private readonly array $hints,
    ) {}

    public function schedule(WakeupRequest $request): ScheduleResult
    {
        $this->assertContext($request->context);

        if (! isset($this->handlers[$request->kind])) {
            throw new RuntimeException("Wakeup handler [{$request->kind}] is not registered.");
        }

        $scope = $this->scope($request->context);
        $payload = $this->canonical($request->payload);
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $digest = hash('sha256', 'wakeup/1'."\0".$request->kind."\0".$request->requestKey."\0".$request->fireAt->format('Y-m-d\\TH:i:s.u\\Z')."\0".$encodedPayload);

        try {
            $this->connection->table('sys_wakeup_requests')->insert([
                'id' => (string) Str::uuid7(), 'workspace_scope_key' => $scope, 'kind' => $request->kind,
                'request_key' => $request->requestKey, 'request_digest' => $digest, 'fire_at' => $request->fireAt,
                'window_start' => $request->window?->start, 'window_end' => $request->window?->end,
                'period' => $request->window?->period, 'payload' => $encodedPayload,
                'status' => 'pending', 'generation' => 0, 'attempt' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = $this->connection->table('sys_wakeup_requests')
                ->where('workspace_scope_key', $scope)
                ->where('request_key', $request->requestKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return new ScheduleResult(status: hash_equals($existing->request_digest, $digest) ? 'existing' : 'conflict', requestKey: $request->requestKey);
        }

        $this->notifyHints(context: $request->context, fireAt: $request->fireAt);

        return new ScheduleResult(status: 'created', requestKey: $request->requestKey);
    }

    public function cancel(TenantContext $context, string $requestKey): CancelResult
    {
        $this->assertContext($context);

        return $this->connection->transaction(function () use ($context, $requestKey): CancelResult {
            $row = $this->connection->table('sys_wakeup_requests')->where('workspace_scope_key', $this->scope($context))->where('request_key', $requestKey)->lockForUpdate()->first();

            if ($row === null) {
                return new CancelResult(status: 'not-found');
            }

            if ($row->status === 'completed') {
                return new CancelResult(status: 'already-completed');
            }

            if ($row->status === 'cancelled') {
                return new CancelResult(status: 'already-cancelled');
            }
            $this->connection->table('sys_wakeup_requests')->where('id', $row->id)->update(['status' => 'cancelled', 'lease_until' => null, 'updated_at' => now()]);

            return new CancelResult(status: 'cancelled');
        });
    }

    private function assertContext(TenantContext $context): void
    {
        $current = $this->contexts->current();

        if ($current->account->id !== $context->account->id || $current->workspace?->id !== $context->workspace?->id) {
            throw new RuntimeException('Wakeup request context does not match the current tenant context.');
        }
    }

    private function scope(TenantContext $context): string
    {
        return $context->workspace === null ? 'account' : "workspace:{$context->workspace->id}";
    }

    /** @param array<array-key, mixed> $value
     * @return array<string, mixed> */
    private function canonical(array $value): array
    {
        if (($value !== [] && array_is_list($value)) || count($value) > 256) {
            throw new RuntimeException('Wakeup payload must be a bounded object map.');
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '') {
                throw new RuntimeException('Wakeup payload contains an unsupported value.');
            }

            $value[$key] = $this->canonicalValue(value: $item, depth: 1);
        }

        if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 65_536) {
            throw new RuntimeException('Wakeup payload exceeds 64KiB.');
        }

        return $value;
    }

    private function canonicalValue(mixed $value, int $depth): mixed
    {
        if ($depth > 8) {
            throw new RuntimeException('Wakeup payload nesting exceeds eight levels.');
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw new RuntimeException('Wakeup payload contains an unsupported value.');
        }

        if (count($value) > 256) {
            throw new RuntimeException('Wakeup payload contains too many values.');
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalValue(value: $item, depth: $depth + 1),
                $value,
            );
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || $key === '') {
                throw new RuntimeException('Wakeup payload contains an unsupported key.');
            }
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalValue(value: $item, depth: $depth + 1);
        }

        return $value;
    }

    private function notifyHints(TenantContext $context, DateTimeImmutable $fireAt): void
    {
        if ($this->connection->transactionLevel() > 0) {
            $this->connection->afterCommit(function () use ($context, $fireAt): void {
                $this->dispatchHints(context: $context, fireAt: $fireAt);
            });

            return;
        }

        $this->dispatchHints(context: $context, fireAt: $fireAt);
    }

    private function dispatchHints(TenantContext $context, DateTimeImmutable $fireAt): void
    {
        foreach ($this->hints as $hint) {
            $hint->scheduled(context: $context, fireAt: $fireAt);
        }
    }
}
