<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
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
use Illuminate\Database\Query\Builder;
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
        private DataScopeFactory $currentScopes,
        private CurrentWorkScope $current,
    ) {}

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        $due = $this->frozen($context)->min('period_end');

        return $due === null ? null : $this->date($due);
    }

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult
    {
        $runs = $this->frozen($context)
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
                $encoded = NotificationValue::text($run->scope);
                $scope = $this->scopes->decode(JsonDocuments::map(JsonDocuments::capture(json_decode($encoded, true, flags: JSON_THROW_ON_ERROR))));

                if ($scope->accountId !== $context->account->id || ! hash_equals(NotificationValue::text($run->scope_key), hash('sha256', json_encode($this->scopes->encode($scope), JSON_THROW_ON_ERROR)))) {
                    throw new InvalidArgumentException('The digest run scope does not match its owner or fingerprint.');
                }

                $id = NotificationValue::text($run->id);
                $window = new WakeupWindow(start: $this->date($run->period_start), end: $this->date($run->period_end), period: NotificationValue::text($run->period));
                $proof = new StoredWorkProof(
                    owner: DigestRunIdentity::Owner,
                    reference: DigestRunIdentity::reference(runId: $id, start: $window->start, end: $window->end, period: $window->period),
                    seal: NotificationValue::text($run->scope_seal),
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
                $result = $this->runner->runStored(scope: $scope, proof: $proof, work: fn () => ($this->scheduler)()->schedule($request));
            } catch (DateException|InvalidArgumentException|JsonException|ScopeStale) {
                $this->connection->transaction(function () use ($context, $run): void {
                    $this->current->assert(scope: $this->currentScopes->forCurrentPrincipal($context), connection: $this->connection);
                    $this->frozen($context)->where('id', $run->id)->update(['state' => 'quarantined']);
                });
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

    private function frozen(TenantContext $context): Builder
    {
        $scope = $this->currentScopes->forCurrentPrincipal($context);
        $this->current->assert(scope: $scope, connection: $this->connection);

        return $this->connection->table('ntf_digest_runs')->where('account_id', $scope->accountId)
            ->where('account_incarnation', $scope->accountIncarnation)->where('state', 'frozen');
    }

    private function date(mixed $value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $date = $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable(NotificationValue::text($value), $utc);

        return $date->setTimezone($utc);
    }
}
