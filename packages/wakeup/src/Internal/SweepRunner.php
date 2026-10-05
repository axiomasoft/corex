<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Data\SweepResult;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

final class SweepRunner implements DueWakeupSweeper
{
    /** @param list<DueWakeupSource> $dueSources */
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContextResolver $contexts,
        private readonly WakeupHandlerRegistry $handlers,
        private readonly array $dueSources,
        private readonly int $leaseSeconds,
        private readonly int $maxAttempts,
        private readonly ClockInterface $clock,
    ) {}

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
            $sourceResult = $source->sweep($context, $this->clock->now(), $sourceLimit);
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
            $now = $this->clock->now();
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
                    'lease_until' => $now->add(new DateInterval("PT{$this->leaseSeconds}S")),
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
                $now = $this->clock->now();
                $row = $this->connection->table('sys_wakeup_requests')->where('id', $id)->lockForUpdate()->first();

                if ($row === null
                    || $row->status !== 'running'
                    || (int) $row->generation !== $generation
                    || $row->lease_until === null
                    || $this->date($row->lease_until) <= $now) {
                    return 'skipped';
                }
                $handler = $this->handlers->handler((string) $row->kind);

                if ($handler === null) {
                    $this->terminalFailure(id: $id, code: 'handler-unavailable', now: $now);

                    return 'failed';
                }
                $payload = $this->payload($row);

                if ($payload['status'] !== 'ready' && $payload['status'] !== 'legacy-admitted') {
                    $this->terminalFailure(id: $id, code: $payload['status'], now: $now);

                    return 'failed';
                }

                $handler->handle(new WakeupDelivery(
                    request: $this->request(context: $context, row: $row, payload: $payload['data']),
                    generation: $generation,
                    attempt: (int) $row->attempt,
                ));
                $this->connection->table('sys_wakeup_requests')->where('id', $id)->where('status', 'running')->where('generation', $generation)->update(['status' => 'completed', 'completed_at' => $now, 'lease_until' => null, 'updated_at' => $now]);

                return 'completed';
            });
        } catch (Throwable) {
            $row = $this->connection->table('sys_wakeup_requests')->where('id', $id)->first();

            if ($row === null) {
                return 'skipped';
            }
            $now = $this->clock->now();
            $attempt = (int) $row->attempt;
            $backoffSeconds = min(300, 2 ** $attempt);
            $retry = $attempt >= $this->maxAttempts ? null : $now->add(new DateInterval("PT{$backoffSeconds}S"));
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
                    'updated_at' => $now,
                ]);

            return $retry === null ? 'failed' : 'retried';
        }
    }

    /** @return array{status: 'ready'|'legacy-admitted'|'handler-unavailable'|'handler-stamp-missing'|'handler-version-mismatch', data?: array<string, mixed>} */
    private function payload(object $row): array
    {
        try {
            $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['status' => 'handler-stamp-missing'];
        }

        return $this->handlers->inspectPayload(kind: (string) $row->kind, payload: $payload);
    }

    /** @param array<string, mixed> $payload */
    private function request(TenantContext $context, object $row, array $payload): WakeupRequest
    {
        $window = $row->window_start === null ? null : new WakeupWindow(start: $this->date($row->window_start), end: $this->date($row->window_end), period: $row->period);

        return new WakeupRequest(context: $context, kind: $row->kind, requestKey: $row->request_key, fireAt: $this->date($row->fire_at), window: $window, payload: JsonDocuments::capture($payload));
    }

    private function terminalFailure(string $id, string $code, DateTimeImmutable $now): void
    {
        $this->connection->table('sys_wakeup_requests')->where('id', $id)->update([
            'status' => 'failed',
            'attempt' => $this->maxAttempts,
            'retry_at' => null,
            'lease_until' => null,
            'failure_code' => $code,
            'updated_at' => $now,
        ]);
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable((string) $value);
    }
}
