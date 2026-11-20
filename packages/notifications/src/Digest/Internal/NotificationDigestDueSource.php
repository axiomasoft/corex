<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use Closure;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\SweepResult;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use DateException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class NotificationDigestDueSource implements DueWakeupSource
{
    /** @param Closure(): WakeupScheduler $scheduler */
    public function __construct(
        private ConnectionInterface $connection,
        private DataScopeCodec $scopes,
        private StoredWorkRunner $runner,
        private Closure $scheduler,
    ) {}

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        $due = $this->connection->table('ntf_digest_runs')->where('state', 'frozen')->min('period_end');

        return $due === null ? null : $this->date($due);
    }

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult
    {
        $runs = $this->connection->table('ntf_digest_runs')
            ->where('state', 'frozen')
            ->where('period_end', '<=', DigestRunIdentity::timestamp($now))
            ->orderBy('period_end')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $recovered = $failed = 0;

        foreach ($runs as $run) {
            // Recreate only a missing immutable request for this concrete run.
            // Existing cancelled, leased, retried or quarantined requests retain
            // their admission state; only the durable sweeper invokes handlers.
            try {
                $encoded = Value::text($run->scope);
                $scope = $this->scopes->decode(JsonDocuments::map(JsonDocuments::capture(json_decode($encoded, true, flags: JSON_THROW_ON_ERROR))));

                if ($scope->accountId !== $context->account->id || ! hash_equals(Value::text($run->scope_key), hash('sha256', json_encode($this->scopes->encode($scope), JSON_THROW_ON_ERROR)))) {
                    throw new InvalidArgumentException('The digest run scope does not match its owner or fingerprint.');
                }

                $id = Value::text($run->id);
                $window = new WakeupWindow(start: $this->date($run->period_start), end: $this->date($run->period_end), period: Value::text($run->period));
                $proof = new StoredWorkProof(
                    DigestRunIdentity::Owner,
                    DigestRunIdentity::reference($id, $window->start, $window->end, $window->period),
                    Value::text($run->scope_seal),
                );
                $request = new WakeupRequest(
                    scope: $scope,
                    kind: SqlDigestBuilder::HandlerKind,
                    handlerVersion: SqlDigestBuilder::HandlerVersion,
                    requestKey: 'notification.digest:'.$id,
                    fireAt: $window->end,
                    window: $window,
                    payload: JsonDocuments::capture(['run_id' => $id]),
                );
                $result = $this->runner->runStored($scope, $proof, fn () => ($this->scheduler)()->schedule($request));
            } catch (DateException|InvalidArgumentException|JsonException|ScopeStale) {
                $this->connection->table('ntf_digest_runs')->where('id', $run->id)->where('state', 'frozen')->update(['state' => 'quarantined']);
                $failed++;

                continue;
            } catch (ScopeContextMismatch) {
                $failed++;

                continue;
            }
            $recovered += $result->status === 'created' ? 1 : 0;
            $failed += $result->status === 'conflict' ? 1 : 0;
        }

        return new SweepResult(claimed: count($runs), completed: 0, retried: $recovered, failed: $failed, skipped: count($runs) - $recovered - $failed, remainingDue: count($runs) === $limit, nextFireAt: $this->nextDue($context));
    }

    private function date(mixed $value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $date = $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable(Value::text($value), $utc);

        return $date->setTimezone($utc);
    }
}
