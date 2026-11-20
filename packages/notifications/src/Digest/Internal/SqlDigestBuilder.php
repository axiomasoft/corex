<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestRef;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Packman\Config\Exceptions\InvalidConfiguration;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlDigestBuilder implements DigestBuilder
{
    public const string HandlerKind = 'notification.digest.v1';

    public const string HandlerVersion = 'notification-digest/1';

    public function __construct(
        private ConnectionInterface $connection,
        private DataScopeFactory $scopes,
        private CurrentWorkScope $currentScope,
        private DataScopeCodec $scopeCodec,
        private StoredWorkIssuer $issuer,
        private ?WakeupScheduler $wakeup,
        private bool $enabled,
        private ClockInterface $clock,
    ) {
        if ($this->enabled && $this->wakeup === null) {
            throw new InvalidConfiguration([
                'corex-notifications.digest.enabled requires a bound CoreX\Scheduling\Contracts\WakeupScheduler',
            ]);
        }
    }

    public function build(NotificationContext $context, DigestWindow $window): ?DigestRef
    {
        if ($context->actorId === null || $context->actorId === '') {
            throw new NotificationAccessDenied('Digest building requires an authenticated recipient.');
        }
        $scope = $this->scopes->forCurrentPrincipal($context->tenant);
        $this->currentScope->assert($scope, $this->connection);

        if ($scope->principalKind !== $context->actorKind || $scope->principalId !== $context->actorId) {
            throw new NotificationAccessDenied('Digest building requires the active recipient.');
        }

        $recipient = new RecipientReference(kind: $context->actorKind, id: $context->actorId);
        $encodedScope = json_encode($this->scopeCodec->encode($scope), JSON_THROW_ON_ERROR);
        $scopeKey = hash('sha256', $encodedScope);

        return $this->connection->transaction(function () use ($scope, $encodedScope, $scopeKey, $recipient, $window): ?DigestRef {
            $this->currentScope->assert($scope, $this->connection);
            $runs = $this->connection->table('ntf_digest_runs')->where('scope_key', $scopeKey)->where('recipient_kind', $recipient->kind)->where('recipient_id', $recipient->id)->where('period', $window->period)->where('period_start', DigestRunIdentity::timestamp($window->start))->orderByDesc('batch_seq')->lockForUpdate()->get();
            $items = $this->connection->table('ntf_notifications')
                ->where('recipient_kind', $recipient->kind)
                ->where('recipient_id', $recipient->id)
                ->whereNull('cancelled_at')
                ->where('created_at', '>=', $window->start)
                ->where('created_at', '<', $window->end)
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('ntf_digest_items')
                        ->whereColumn('ntf_digest_items.notification_id', 'ntf_notifications.id')
                        ->where('ntf_digest_items.channel', 'in_app');
                })
                ->orderBy('id')
                ->limit(100)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                if ($runs->isEmpty()) {
                    return null;
                }

                $run = $runs->first();

                return new DigestRef(id: Value::text($run->id), recipient: $recipient, window: $window, batchSequence: Value::integer($run->batch_seq));
            }
            $ids = Value::strings($items->pluck('id')->all());
            $runId = (string) Str::uuid7();
            $proof = $this->issuer->issue($scope, DigestRunIdentity::Owner, DigestRunIdentity::reference($runId, $window->start, $window->end, $window->period));
            $batchSequence = $runs->isEmpty() ? 0 : Value::integer($runs->first()->batch_seq) + 1;
            $this->connection->table('ntf_digest_runs')->insert(['id' => $runId, 'scope_key' => $scopeKey, 'scope' => $encodedScope, 'scope_seal' => $proof->seal, 'recipient_kind' => $recipient->kind, 'recipient_id' => $recipient->id, 'period' => $window->period, 'period_start' => DigestRunIdentity::timestamp($window->start), 'period_end' => DigestRunIdentity::timestamp($window->end), 'batch_seq' => $batchSequence, 'membership_hash' => hash('sha256', implode("\0", $ids)), 'state' => 'frozen', 'created_at' => $this->clock->now()]);
            foreach ($ids as $id) {
                $this->connection->table('ntf_digest_items')->insert(['id' => (string) Str::uuid7(), 'run_id' => $runId, 'notification_id' => $id, 'channel' => 'in_app']);
            }

            if ($this->enabled) {
                $wakeup = $this->wakeup ?? throw new InvalidConfiguration([
                    'corex-notifications.digest.enabled requires a bound CoreX\Scheduling\Contracts\WakeupScheduler',
                ]);
                $result = $wakeup->schedule(new WakeupRequest(
                    scope: $scope,
                    kind: self::HandlerKind,
                    handlerVersion: self::HandlerVersion,
                    requestKey: "notification.digest:{$runId}",
                    fireAt: $window->end,
                    window: new WakeupWindow(start: $window->start, end: $window->end, period: $window->period),
                    payload: JsonDocuments::capture(['run_id' => $runId]),
                ));

                if (! in_array($result->status, ['created', 'existing'], true)) {
                    throw new NotificationAccessDenied('The digest wakeup could not be scheduled in the current scope.');
                }
            }

            return new DigestRef(id: $runId, recipient: $recipient, window: $window, batchSequence: $batchSequence);
        });
    }
}
