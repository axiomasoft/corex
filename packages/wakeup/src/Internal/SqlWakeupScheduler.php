<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Internal;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Data\CancelResult;
use CoreX\Wakeup\Data\ScheduleResult;
use CoreX\Wakeup\Data\WakeupRequest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

final class SqlWakeupScheduler implements WakeupScheduler
{
    /** @param array<string, WakeupHandler> $handlers */
    public function __construct(private readonly ConnectionInterface $connection, private readonly TenantContextResolver $contexts, private readonly array $handlers) {}

    public function schedule(WakeupRequest $request): ScheduleResult
    {
        $this->assertContext($request->context);

        if (! isset($this->handlers[$request->kind])) {
            throw new RuntimeException("Wakeup handler [{$request->kind}] is not registered.");
        }

        $scope = $this->scope($request->context);
        $payload = $this->canonical($request->payload);
        $digest = hash('sha256', $request->kind."\0".$request->requestKey."\0".$request->fireAt->format('Y-m-d\\TH:i:s.u\\Z')."\0".json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            $this->connection->table('sys_wakeup_requests')->insert([
                'id' => (string) Str::uuid7(), 'workspace_scope_key' => $scope, 'kind' => $request->kind,
                'request_key' => $request->requestKey, 'request_digest' => $digest, 'fire_at' => $request->fireAt,
                'window_start' => $request->window?->start, 'window_end' => $request->window?->end,
                'period' => $request->window?->period, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'pending', 'generation' => 0, 'attempt' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = $this->connection->table('sys_wakeup_requests')->where('workspace_scope_key', $scope)->where('kind', $request->kind)->where('request_key', $request->requestKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            return new ScheduleResult(status: hash_equals($existing->request_digest, $digest) ? 'existing' : 'conflict', requestKey: $request->requestKey);
        }

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
        ksort($value);
        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '' || ! is_null($item) && ! is_bool($item) && ! is_int($item) && ! is_string($item) && ! is_array($item)) {
                throw new RuntimeException('Wakeup payload contains an unsupported value.');
            }

            if (is_array($item)) {
                $value[$key] = array_is_list($item) ? $item : $this->canonical($item);
            }
        }

        return $value;
    }
}
