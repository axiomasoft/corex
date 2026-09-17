<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Internal;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Contracts\DueSource;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Contracts\WakeupSweeper;
use CoreX\Wakeup\Data\SweepResult;
use CoreX\Wakeup\Data\WakeupDelivery;
use CoreX\Wakeup\Data\WakeupRequest;
use CoreX\Wakeup\Data\WakeupWindow;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use RuntimeException;
use Throwable;

final class SweepRunner implements WakeupSweeper
{
    /** @param array<string, WakeupHandler> $handlers
     * @param list<DueSource> $dueSources */
    public function __construct(private readonly Connection $connection, private readonly TenantContextResolver $contexts, private readonly array $handlers, private readonly array $dueSources, private readonly int $leaseSeconds, private readonly int $maxAttempts) {}

    public function sweep(TenantContext $context, int $limit): SweepResult
    {
        if ($limit < 1 || $limit > 1_000 || $this->contexts->current()->account->id !== $context->account->id) {
            throw new RuntimeException('Invalid wakeup sweep context or limit.');
        }
        $claims = $this->claimDue(limit: $limit);
        $completed = $retried = $failed = $skipped = 0;
        foreach ($claims as $claim) {
            $result = $this->run(context: $context, id: $claim['id'], generation: $claim['generation']);
            $completed += $result === 'completed' ? 1 : 0;
            $retried += $result === 'retried' ? 1 : 0;
            $failed += $result === 'failed' ? 1 : 0;
            $skipped += $result === 'skipped' ? 1 : 0;
        }
        $sourceLimit = max(1, intdiv($limit, count($this->dueSources) + 1));
        foreach ($this->dueSources as $source) {
            $sourceResult = $source->sweep($context, new DateTimeImmutable('now'), $sourceLimit);
            $completed += $sourceResult->completed;
            $retried += $sourceResult->retried;
            $failed += $sourceResult->failed;
            $skipped += $sourceResult->skipped;
        }
        $next = $this->nextDue($context);

        return new SweepResult(claimed: count($claims), completed: $completed, retried: $retried, failed: $failed, skipped: $skipped, remainingDue: count($claims) === $limit, nextFireAt: $next);
    }

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        if ($this->contexts->current()->account->id !== $context->account->id) {
            throw new RuntimeException('Wakeup context does not match the current tenant context.');
        }
        $value = $this->connection->table('sys_wakeup_requests')->whereIn('status', ['pending', 'failed'])->where('attempt', '<', $this->maxAttempts)->min('fire_at');
        $due = $value === null ? null : new DateTimeImmutable((string) $value);

        foreach ($this->dueSources as $source) {
            $candidate = $source->nextDue($context);

            if ($candidate !== null && ($due === null || $candidate < $due)) {
                $due = $candidate;
            }
        }

        return $due;
    }

    /** @return list<array{id: string, generation: int}> */
    private function claimDue(int $limit): array
    {
        return $this->connection->transaction(function () use ($limit): array {
            $now = now();
            $this->connection->table('sys_wakeup_requests')
                ->where('status', 'running')
                ->where('lease_until', '<=', $now)
                ->where('attempt', '<', $this->maxAttempts)
                ->update(['status' => 'failed', 'retry_at' => $now, 'lease_until' => null, 'updated_at' => $now]);
            $this->connection->table('sys_wakeup_requests')
                ->where('status', 'running')
                ->where('lease_until', '<=', $now)
                ->where('attempt', '>=', $this->maxAttempts)
                ->update(['status' => 'failed', 'retry_at' => null, 'lease_until' => null, 'failure_code' => 'lease-expired', 'updated_at' => $now]);

            $query = $this->connection->table('sys_wakeup_requests')
                ->whereIn('status', ['pending', 'failed'])
                ->where('attempt', '<', $this->maxAttempts)
                ->where('fire_at', '<=', $now)
                ->where(static fn ($query) => $query->whereNull('retry_at')->orWhere('retry_at', '<=', $now))
                ->orderBy('fire_at')
                ->orderBy('id')
                ->limit($limit);

            if ($this->connection->getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $rows = $query->get();
            $claims = [];

            foreach ($rows as $row) {
                $generation = (int) $row->generation + 1;
                $this->connection->table('sys_wakeup_requests')->where('id', $row->id)->update([
                    'status' => 'running',
                    'generation' => $generation,
                    'attempt' => (int) $row->attempt + 1,
                    'lease_until' => $now->copy()->addSeconds($this->leaseSeconds),
                    'updated_at' => $now,
                ]);
                $claims[] = ['id' => $row->id, 'generation' => $generation];
            }

            return $claims;
        });
    }

    private function run(TenantContext $context, string $id, int $generation): string
    {
        try {
            return $this->connection->transaction(function () use ($context, $generation, $id): string {
                $row = $this->connection->table('sys_wakeup_requests')->where('id', $id)->lockForUpdate()->first();

                if ($row === null
                    || $row->status !== 'running'
                    || (int) $row->generation !== $generation
                    || $row->lease_until === null
                    || $this->date($row->lease_until) <= new DateTimeImmutable('now')) {
                    return 'skipped';
                }
                $handler = $this->handlers[$row->kind] ?? null;

                if ($handler === null) {
                    $this->connection->table('sys_wakeup_requests')->where('id', $id)->update(['status' => 'failed', 'attempt' => $this->maxAttempts, 'retry_at' => null, 'failure_code' => 'handler-unavailable', 'updated_at' => now()]);

                    return 'failed';
                }
                $handler->handle(new WakeupDelivery(request: $this->request($context, $row), generation: $generation, attempt: (int) $row->attempt));
                $this->connection->table('sys_wakeup_requests')->where('id', $id)->where('status', 'running')->where('generation', $generation)->update(['status' => 'completed', 'completed_at' => now(), 'lease_until' => null, 'updated_at' => now()]);

                return 'completed';
            });
        } catch (Throwable) {
            $row = $this->connection->table('sys_wakeup_requests')->where('id', $id)->first();

            if ($row === null) {
                return 'skipped';
            }
            $attempt = (int) $row->attempt;
            $retry = $attempt >= $this->maxAttempts ? null : now()->addSeconds(min(300, 2 ** $attempt));
            $this->connection->table('sys_wakeup_requests')
                ->where('id', $id)
                ->where('status', 'running')
                ->where('generation', $generation)
                ->update([
                    'status' => 'failed',
                    'attempt' => $attempt,
                    'retry_at' => $retry,
                    'lease_until' => null,
                    'failure_code' => 'handler-failed',
                    'updated_at' => now(),
                ]);

            return $retry === null ? 'failed' : 'retried';
        }
    }

    private function request(TenantContext $context, object $row): WakeupRequest
    {
        $window = $row->window_start === null ? null : new WakeupWindow(start: $this->date($row->window_start), end: $this->date($row->window_end), period: $row->period);
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);

        return new WakeupRequest(context: $context, kind: $row->kind, requestKey: $row->request_key, fireAt: $this->date($row->fire_at), window: $window, payload: $payload);
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable((string) $value);
    }
}
