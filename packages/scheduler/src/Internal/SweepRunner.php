<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Exceptions\InvalidScopePayload;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Data\SweepResult;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;
use Throwable;

#[Internal]
final readonly class SweepRunner implements DueWakeupSweeper
{
    private const string DUE_TIME = "case when status in ('running', 'cancel-requested') then lease_until when status = 'retry-scheduled' then retry_at else fire_at end";

    /**
     * @param  Closure(class-string<DueWakeupHandler>): mixed  $resolveHandler
     * @param  Closure():list<DueWakeupSource>  $dueSources
     */
    public function __construct(
        private DatabaseManager $databases,
        private TenantContextResolver $contexts,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $current,
        private WorkScopeRunner $runner,
        private StoredWorkRunner $stored,
        private DataScopeCodec $codec,
        private WakeupInput $input,
        private WakeupHandlerRegistry $handlers,
        private Closure $resolveHandler,
        private Closure $dueSources,
        private int $leaseSeconds,
        private int $maxAttempts,
        private ClockInterface $clock,
    ) {}

    public function sweep(TenantContext $context, int $limit): SweepResult
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Wakeup sweep limit must be between 1 and 1000.');
        }
        $owner = $this->scopes->forCurrentPrincipal($context);
        $claims = $this->runner->run(scope: $owner, work: fn (): array => $this->claimDue(owner: $owner, limit: $limit));
        $counts = ['completed' => 0, 'retried' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($claims as $claim) {
            $counts[$this->run(owner: $owner, claim: $claim)]++;
        }

        $context = $this->contexts->current();
        $current = $this->scopes->forCurrentPrincipal($context);
        $this->assertOwner(owner: $owner, scope: $current);
        $sources = ($this->dueSources)();
        $available = $limit - count($claims);
        $claimed = count($claims);
        foreach ($sources as $i => $source) {
            if ($available <= 0) {
                break;
            }
            $batch = max(1, intdiv($available, count($sources) - $i));
            $result = $this->runner->run(scope: $current, work: fn (): SweepResult => $source->sweep($context, $this->clock->now(), $batch));
            $available -= $result->claimed;
            $claimed += $result->claimed;
            $counts['completed'] += $result->completed;
            $counts['retried'] += $result->retried;
            $counts['failed'] += $result->failed;
            $counts['skipped'] += $result->skipped;
        }
        $next = $this->nextDue($context);

        return new SweepResult(claimed: $claimed, completed: $counts['completed'], retried: $counts['retried'], failed: $counts['failed'], skipped: $counts['skipped'],
            remainingDue: $next !== null && $next <= $this->clock->now(), nextFireAt: $next);
    }

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        $scope = $this->scopes->forCurrentPrincipal($context);
        $db = $this->connection($scope);
        $value = $this->runnable(db: $db, owner: $scope)->min($db->raw(self::DUE_TIME));
        $due = $value === null ? null : WakeupInput::instant($value);

        foreach (($this->dueSources)() as $source) {
            $candidate = $source->nextDue($context);

            if ($candidate !== null && ($due === null || $candidate < $due)) {
                $due = $candidate;
            }
        }

        return $due;
    }

    private function runnable(Connection $db, DataScope $owner): Builder
    {
        return $db->table('sys_wakeup_requests')->where('account_id', $owner->accountId)
            ->where(function (Builder $rows): void {
                $rows->where(fn (Builder $row) => $row->whereIn('status', ['pending', 'retry-scheduled'])->where('attempt', '<', $this->maxAttempts))
                    ->orWhereIn('status', ['running', 'cancel-requested']);
            });
    }

    /** @return list<WakeupClaim> */
    private function claimDue(DataScope $owner, int $limit): array
    {
        $db = $this->connection($owner);

        if ($db->transactionLevel() !== 0) {
            throw new RuntimeException('A wakeup sweep must start outside a tenant transaction.');
        }

        return $db->transaction(function () use ($db, $owner, $limit): array {
            $now = $this->clock->now();
            $stamp = WakeupInput::time($now);
            $query = $this->runnable(db: $db, owner: $owner)->whereRaw(self::DUE_TIME.' <= ?', [$stamp])
                ->orderByRaw(self::DUE_TIME)->orderBy('id')->limit($limit);
            $rows = $query->lock($db->getDriverName() === 'pgsql' ? 'for update skip locked' : true)->get();
            $claims = [];
            foreach ($rows as $row) {
                $attempt = Value::integer($row->attempt);
                $update = $db->table('sys_wakeup_requests')->where('id', $row->id);

                if ($row->status === 'cancel-requested' || $attempt >= $this->maxAttempts) {
                    $cancel = $row->status === 'cancel-requested';
                    $update->update(['status' => $cancel ? 'cancelled' : 'exhausted', 'claim_token' => null,
                        'lease_until' => null, 'failure_code' => $cancel ? null : 'lease-expired', 'updated_at' => $stamp]);

                    continue;
                }
                $claim = new WakeupClaim(id: Value::text($row->id), token: (string) Str::uuid7(), generation: Value::integer($row->generation) + 1,
                    attempt: $attempt + 1, scope: Value::text($row->data_scope), digest: Value::text($row->request_digest), seal: Value::text($row->scope_seal), workspace: Value::text($row->workspace_scope_key),
                    deadline: $now->add(new DateInterval("PT{$this->leaseSeconds}S")));
                $update->update(['status' => 'running', 'claim_token' => $claim->token, 'generation' => $claim->generation,
                    'attempt' => $claim->attempt, 'lease_until' => WakeupInput::time($claim->deadline),
                    'retry_at' => null, 'failure_code' => null, 'updated_at' => $stamp]);
                $claims[] = $claim;
            }

            return $claims;
        });
    }

    private function run(DataScope $owner, WakeupClaim $claim): string
    {
        try {
            $fields = json_decode($claim->scope, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($fields) || array_any(array_keys($fields), static fn (int|string $key): bool => is_int($key))) {
                throw InvalidScopePayload::missing('scope');
            }
            $scope = $this->codec->decode(Value::map($fields));
            $this->assertOwner(owner: $owner, scope: $scope);

            if (WakeupInput::workspace($scope) !== $claim->workspace) {
                throw new ScopeContextMismatch('Claim has an inconsistent workspace.');
            }

            return $this->stored->runStored(scope: $scope, proof: $claim->proof(), work: function (TenantContext $context) use ($scope, $claim): string {
                $db = $this->connection($scope);

                return $this->deliver(db: $db, context: $context, scope: $scope, claim: $claim);
            }, attempt: $claim->operation());
        } catch (WakeupClaimLost) {
            return 'skipped';
        } catch (InvalidScopePayload|JsonException|ScopeContextMismatch|ScopeStale) {
            // Only a currently admitted sweep of the same physical owner may quarantine a row.
            $current = $this->scopes->forCurrentPrincipal($this->contexts->current());
            $this->assertOwner(owner: $owner, scope: $current);

            return $this->runner->run(scope: $current, work: function () use ($current, $claim): string {
                $db = $this->connection($current);

                return $db->transaction(fn (): string => $this->quarantine(db: $db, claim: $claim, reason: 'scope-unavailable'));
            });
        }
    }

    private function deliver(Connection $db, TenantContext $context, DataScope $scope, WakeupClaim $claim): string
    {
        try {
            return $db->transaction(function () use ($db, $context, $scope, $claim): string {
                $row = $claim->row($db)->lockForUpdate()->first();

                if ($row === null || $row->lease_until === null || WakeupInput::instant($row->lease_until) <= $this->clock->now()) {
                    return 'skipped';
                }

                if ($row->status === 'cancel-requested') {
                    $claim->row($db)->where('status', 'cancel-requested')->update(['status' => 'cancelled', 'claim_token' => null,
                        'lease_until' => null, 'updated_at' => WakeupInput::time($this->clock->now())]);

                    return 'skipped';
                }

                if ($row->status !== 'running') {
                    return 'skipped';
                }

                try {
                    $envelope = json_decode(Value::text($row->payload), flags: JSON_THROW_ON_ERROR);
                    $metadata = $envelope instanceof stdClass ? ($envelope->_corex ?? null) : null;
                    $data = $envelope instanceof stdClass ? ($envelope->data ?? null) : null;
                    $payload = $this->handlers->inspectPayload(kind: Value::text($row->kind), payload: [
                        '_corex' => $metadata instanceof stdClass ? ['schema' => $metadata->schema ?? null, 'extension' => (array) ($metadata->extension ?? null)] : null,
                        'data' => $data instanceof stdClass || $data === [] ? (array) $data : null,
                    ]);

                    if ($payload['status'] !== 'ready') {
                        return $this->quarantine(db: $db, claim: $claim, reason: $payload['status']);
                    }
                    $request = $this->request(row: $row, scope: $scope, data: $payload['data'] ?? []);

                    if ($row->account_id !== $scope->accountId || $row->account_incarnation !== $scope->accountIncarnation
                        || $row->workspace_scope_key !== WakeupInput::workspace($scope) || $row->data_scope !== $claim->scope
                        || ! hash_equals($claim->digest, $this->input->digest(request: $request, data: WakeupInput::payload($request->payload)))) {
                        return $this->quarantine(db: $db, claim: $claim, reason: 'request-digest-mismatch');
                    }
                } catch (InvalidArgumentException|JsonException|RuntimeException $e) {
                    if ($e instanceof QueryException) {
                        throw $e;
                    }

                    return $this->quarantine(db: $db, claim: $claim, reason: 'request-invalid');
                }

                $handler = $this->handlers->handler(kind: $request->kind, resolve: $this->resolveHandler);

                if ($handler === null) {
                    return $this->quarantine(db: $db, claim: $claim, reason: 'handler-unavailable');
                }
                $this->current->assert(scope: $scope, connection: $db);

                if (WakeupInput::instant($row->lease_until) <= $this->clock->now()) {
                    return 'skipped';
                }
                $handler->handle(new WakeupDelivery(request: $request, context: $context, generation: $claim->generation, attempt: $claim->attempt, idempotencyKey: 'wakeup:'.$claim->id));
                $this->current->assert(scope: $scope, connection: $db);
                $stamp = WakeupInput::time($this->clock->now());
                $updated = $this->active(db: $db, claim: $claim, stamp: $stamp)->update(['status' => 'completed', 'completed_at' => $stamp,
                    'lease_until' => null, 'claim_token' => null, 'updated_at' => $stamp]);

                if ($updated !== 1) {
                    throw new WakeupClaimLost;
                }

                return 'completed';
            });
        } catch (WakeupClaimLost|ScopeContextMismatch|ScopeStale $error) {
            throw $error;
        } catch (Throwable) {
            $this->current->assert(scope: $scope, connection: $db);
            $now = $this->clock->now();
            $stamp = WakeupInput::time($now);
            $retry = $claim->attempt < $this->maxAttempts;
            $delay = min(300, 2 ** min(9, $claim->attempt));
            $changed = $this->active(db: $db, claim: $claim, stamp: $stamp)->update(['status' => $retry ? 'retry-scheduled' : 'exhausted',
                'retry_at' => $retry ? WakeupInput::time($now->add(new DateInterval("PT{$delay}S"))) : null,
                'failure_code' => 'handler-failed', 'lease_until' => null, 'claim_token' => null, 'updated_at' => $stamp]);

            return $changed === 1 ? ($retry ? 'retried' : 'failed') : 'skipped';
        }
    }

    private function active(Connection $db, WakeupClaim $claim, string $stamp): Builder
    {
        return $claim->row($db)->where('status', 'running')->where('lease_until', '>', $stamp);
    }

    private function quarantine(Connection $db, WakeupClaim $claim, string $reason): string
    {
        $stamp = WakeupInput::time($this->clock->now());
        $changed = $this->active(db: $db, claim: $claim, stamp: $stamp)->update(['status' => 'quarantined', 'retry_at' => null,
            'failure_code' => $reason, 'lease_until' => null, 'claim_token' => null, 'updated_at' => $stamp]);

        return $changed === 1 ? 'failed' : 'skipped';
    }

    /** @param array<string,mixed> $data */
    private function request(object $row, DataScope $scope, array $data): WakeupRequest
    {
        $start = Value::field(row: $row, name: 'window_start');
        $end = Value::field(row: $row, name: 'window_end');
        $period = Value::field(row: $row, name: 'period');

        if (($start === null) !== ($end === null) || ($start === null) !== ($period === null)) {
            throw new RuntimeException('Incomplete wakeup window.');
        }
        $window = $start === null ? null : new WakeupWindow(start: WakeupInput::instant($start), end: WakeupInput::instant($end), period: Value::text($period));
        $kind = Value::text(Value::field(row: $row, name: 'kind'));

        return new WakeupRequest(scope: $scope, kind: $kind, handlerVersion: $this->handlers->definition($kind)->version ?? '', requestKey: Value::text(Value::field(row: $row, name: 'request_key')),
            fireAt: WakeupInput::instant(Value::field(row: $row, name: 'fire_at')), window: $window, payload: new JsonDocument(json_encode((object) $data, JSON_THROW_ON_ERROR)));
    }

    private function assertOwner(DataScope $owner, DataScope $scope): void
    {
        if ($scope->accountId !== $owner->accountId || $scope->bindingId !== $owner->bindingId
            || $scope->deploymentId !== $owner->deploymentId || $scope->bindingGeneration !== $owner->bindingGeneration
            || $scope->restoreEpoch !== $owner->restoreEpoch || $scope->accountIncarnation !== $owner->accountIncarnation) {
            throw new ScopeContextMismatch('The wakeup belongs to a different physical owner.');
        }
    }

    private function connection(DataScope $scope): Connection
    {
        $connection = $this->databases->connection();
        $this->current->assert(scope: $scope, connection: $connection);

        return $connection;
    }
}
