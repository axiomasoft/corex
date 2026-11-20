<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\DeliveryClaim;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Delivery\Enums\DeliveryOutcomeKind;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class DeliveryStore
{
    /** @param Closure(): ProviderEffectRecovery $receiptRecovery */
    public function __construct(
        private Connection $connection,
        private int $claimSeconds,
        private int $maxAttempts,
        private ClockInterface $clock,
        private CurrentWorkScope $current,
        private DataScopeFactory $scopes,
        private DataScopeCodec $codec,
        private TenantContextResolver $tenants,
        private Closure $receiptRecovery,
    ) {}

    /** @return list<string> */
    public function dueIds(int $limit = 100): array
    {
        return array_values($this->runnable($this->lane($this->sweepScope()))
            ->orderBy('next_attempt')->orderBy('id')->limit($limit)
            ->pluck('id')->map(NotificationValue::text(...))->all());
    }

    public function recoverExpired(): int
    {
        $scope = $this->sweepScope();

        return $this->connection->transaction(function () use ($scope): int {
            $this->current->assert($scope, $this->connection);
            $now = $this->clock->now();
            $expired = $this->lane($scope)->where('lease_until', '<=', $now);
            $possibleSend = (clone $expired)->where('state', 'sending')->update([
                'state' => 'unknown', 'lease_until' => null, 'claim_token' => null, 'next_attempt' => null,
            ]);
            $claimed = (clone $expired)->where('state', 'claimed');
            $beforeSend = (clone $claimed)->where('attempts', '<', $this->maxAttempts)->update([
                'state' => 'retry-scheduled', 'lease_until' => null, 'claim_token' => null, 'next_attempt' => $now,
            ]);
            $exhausted = $claimed->where('attempts', '>=', $this->maxAttempts)->update([
                'state' => 'exhausted', 'lease_until' => null, 'claim_token' => null, 'next_attempt' => null,
            ]);

            return $possibleSend + $beforeSend + $exhausted;
        });
    }

    public function claim(string $deliveryId): ?DeliveryClaim
    {
        $scope = $this->sweepScope();

        return $this->connection->transaction(function () use ($scope, $deliveryId): ?DeliveryClaim {
            $this->current->assert($scope, $this->connection);
            $query = $this->runnable($this->lane($scope)->where('id', $deliveryId));
            $row = $query->lock($this->connection->getDriverName() === 'pgsql' ? 'for update skip locked' : true)->first();

            if ($row === null) {
                return null;
            }

            $token = (string) Str::uuid7();
            $epoch = NotificationValue::integer(NotificationValue::field($row, 'claim_epoch')) + 1;
            $attempts = NotificationValue::integer(NotificationValue::field($row, 'attempts')) + 1;
            $deadline = $this->clock->now()->add(new DateInterval('PT'.$this->claimSeconds.'S'));
            $claim = $this->claimFromRow($row, $scope, $token, $epoch, $attempts, $deadline);

            if ($claim === null) {
                $this->lane($scope)->where('id', $deliveryId)->update(['state' => 'quarantined', 'next_attempt' => null]);

                return null;
            }
            $this->lane($scope)->where('id', $deliveryId)->update([
                'state' => 'claimed',
                'claim_epoch' => $epoch,
                'claim_token' => $token,
                'attempts' => $attempts,
                'lease_until' => $deadline,
            ]);

            return $claim;
        });
    }

    private function claimFromRow(object $row, DataScope $scope, string $token, int $epoch, int $attempts, DateTimeImmutable $deadline): ?DeliveryClaim
    {
        $id = NotificationValue::text(NotificationValue::field($row, 'id'));
        $key = NotificationValue::text(NotificationValue::field($row, 'delivery_key'));
        $channel = NotificationValue::text(NotificationValue::field($row, 'channel'));
        $digest = NotificationValue::text(NotificationValue::field($row, 'message_hash'));
        $driverVersion = NotificationValue::text(NotificationValue::field($row, 'driver_version'));
        $message = NotificationValue::text(NotificationValue::field($row, 'message'));
        $notificationId = NotificationValue::nullableText(NotificationValue::field($row, 'notification_id'));
        $runId = NotificationValue::nullableText(NotificationValue::field($row, 'digest_run_id'));
        $original = $this->codec->decode(NotificationValue::map(json_decode(NotificationValue::text(NotificationValue::field($row, 'scope')), true, flags: JSON_THROW_ON_ERROR)));

        if (($notificationId === null) === ($runId === null) || NotificationValue::field($row, 'template_version') !== '1' || NotificationValue::field($row, 'policy_version') !== '1'
            || ! hash_equals($digest, hash('sha256', $message))
            || NotificationScope::logical($original) !== NotificationScope::logical($scope)
            || NotificationScope::fence($original) !== NotificationScope::fence($scope)) {
            return null;
        }

        return new DeliveryClaim(
            id: $id,
            notificationId: $notificationId,
            digestRunId: $runId,
            channel: $channel,
            deliveryKey: $key,
            epoch: $epoch,
            attempts: $attempts,
            message: JsonDocuments::capture(NotificationValue::map(json_decode($message, true, flags: JSON_THROW_ON_ERROR))),
            messageHash: $digest,
            templateVersion: NotificationValue::text(NotificationValue::field($row, 'template_version')),
            policyVersion: NotificationValue::text(NotificationValue::field($row, 'policy_version')),
            driverVersion: $driverVersion,
            scope: $original,
            proof: new StoredWorkProof(
                DeliveryIntents::Owner, DeliveryIntents::reference($id, $key, $digest, $channel, $driverVersion, $notificationId, $runId),
                NotificationValue::text(NotificationValue::field($row, 'scope_seal')),
            ),
            token: $token,
            deadline: $deadline,
            providerNamespace: NotificationValue::nullableText(NotificationValue::field($row, 'provider_namespace')),
            targetReference: NotificationValue::nullableText(NotificationValue::field($row, 'target_reference')),
        );
    }

    public function freezeTarget(DeliveryClaim $claim, string $namespace, string $target): bool
    {
        return $this->change($claim, 'claimed', ['provider_namespace' => $namespace, 'target_reference' => $target]);
    }

    public function startSending(DeliveryClaim $claim, string $ticketId): bool
    {
        return $this->change($claim, 'claimed', ['state' => 'sending', 'provider_ticket' => $ticketId]);
    }

    /** @param array<string, mixed> $values */
    private function change(DeliveryClaim $claim, string $state, array $values): bool
    {
        return $this->connection->transaction(function () use ($claim, $state, $values): bool {
            $this->current->assert($claim->scope, $this->connection);

            return $this->owned($claim, $state)->update($values) === 1;
        });
    }

    public function complete(DeliveryClaim $claim, DeliveryOutcome $outcome, string $state = 'claimed'): bool
    {
        return $this->connection->transaction(function () use ($claim, $outcome, $state): bool {
            $this->current->assert($claim->scope, $this->connection);

            return $this->persistOutcome($this->owned($claim, $state), $claim->digestRunId, $claim->attempts, $outcome);
        });
    }

    public function skip(DeliveryClaim $claim, string $reason): bool
    {
        return $this->connection->transaction(function () use ($claim, $reason): bool {
            $this->current->assert($claim->scope, $this->connection);

            return $this->finish($this->owned($claim, 'claimed'), $claim->digestRunId, 'skipped', [
                'error' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function quarantine(DeliveryClaim $claim): void
    {
        $scope = $this->sweepScope();
        $this->connection->transaction(function () use ($scope, $claim): void {
            $this->current->assert($scope, $this->connection);
            $this->owned($claim, 'claimed')->update([
                'state' => 'quarantined', 'next_attempt' => null, 'claim_token' => null, 'lease_until' => null,
            ]);
        });
    }

    public function effect(string $id): ?ProviderEffectRef
    {
        $scope = $this->sweepScope();
        $row = $this->lane($scope)->where('id', $id)->whereNotNull('provider_namespace')->first();

        return $row === null ? null : self::effectRef($scope, NotificationValue::text(NotificationValue::field($row, 'provider_namespace')), NotificationValue::text(NotificationValue::field($row, 'delivery_key')));
    }

    public static function effectRef(DataScope $scope, string $namespace, string $key): ProviderEffectRef
    {
        return new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, DeliveryIntents::Owner, $namespace, $key);
    }

    public static function providerDigest(string $messageHash, string $namespace, string $target): string
    {
        return hash('sha256', json_encode([$messageHash, $namespace, $target], JSON_THROW_ON_ERROR));
    }

    public function providerRow(ProviderEffectStatus $status): ?object
    {
        return $this->validatedProviderRow($status, true);
    }

    public function claimPrecededSend(ProviderEffectStatus $status): bool
    {
        $row = $this->validatedProviderRow($status, false);

        return $row !== null && NotificationValue::field($row, 'provider_ticket') !== $status->ticket->id
            && NotificationValue::field($row, 'claim_token') !== $status->claimToken
            && in_array(NotificationValue::field($row, 'state'), ['claimed', 'retry-scheduled', 'cancelled', 'exhausted', 'skipped', 'quarantined'], true);
    }

    private function validatedProviderRow(ProviderEffectStatus $status, bool $linked): ?object
    {
        ($this->receiptRecovery)()->assertReceipt($status, $this->connection);
        $row = $this->providerQuery($status, $linked)->first();

        if ($row === null) {
            return null;
        }

        $target = NotificationValue::text(NotificationValue::field($row, 'target_reference'));
        $message = NotificationValue::text(NotificationValue::field($row, 'message'));
        $hash = NotificationValue::text(NotificationValue::field($row, 'message_hash'));

        return hash_equals($hash, hash('sha256', $message))
            && hash_equals($status->digest, self::providerDigest($hash, $status->effect->providerNamespace, $target)) ? $row : null;
    }

    public function project(ProviderEffectStatus $status, DeliveryOutcome $outcome): ?string
    {
        return $this->connection->transaction(function () use ($status, $outcome): ?string {
            $row = $this->providerRow($status);

            if ($row === null) {
                return $outcome->kind === DeliveryOutcomeKind::Retryable && $this->claimPrecededSend($status)
                    ? $status->ownerRef : null;
            }

            if ($outcome->kind === DeliveryOutcomeKind::Retryable && NotificationValue::integer(NotificationValue::field($row, 'attempts')) > $status->ticket->key->attempt) {
                return $status->ownerRef;
            }

            $state = NotificationValue::text(NotificationValue::field($row, 'state'));
            $query = $this->providerQuery($status)->where('state', $state);

            if ($state === 'sending') {
                $query->where('claim_token', $status->claimToken)->where('lease_until', '<=', $this->clock->now());
            } elseif (! in_array($state, ['unknown', 'accepted'], true)) {
                return $this->sameResult($row, $outcome) ? $status->ownerRef : null;
            }

            if ($state === 'accepted' && $outcome->kind !== DeliveryOutcomeKind::Completed) {
                return $this->sameResult($row, $outcome) ? $status->ownerRef : null;
            }

            return $this->persistOutcome($query, NotificationValue::nullableText(NotificationValue::field($row, 'digest_run_id')), NotificationValue::integer(NotificationValue::field($row, 'attempts')), $outcome)
                ? $status->ownerRef : null;
        });
    }

    private function sameResult(object $row, DeliveryOutcome $outcome): bool
    {
        return match ($outcome->kind) {
            DeliveryOutcomeKind::Completed => NotificationValue::field($row, 'state') === 'completed' && NotificationValue::field($row, 'provider_ref') === $outcome->providerRef,
            DeliveryOutcomeKind::Accepted => NotificationValue::field($row, 'state') === 'accepted' && NotificationValue::field($row, 'provider_ref') === $outcome->providerRef,
            DeliveryOutcomeKind::Retryable => in_array(NotificationValue::field($row, 'state'), ['retry-scheduled', 'exhausted'], true),
            DeliveryOutcomeKind::Permanent => NotificationValue::field($row, 'state') === 'permanently-failed',
            DeliveryOutcomeKind::Unknown => false,
        };
    }

    private function providerQuery(ProviderEffectStatus $status, bool $linked = true): Builder
    {
        return $this->lane($status->ticket->scope)->where('id', $status->ownerRef)
            ->when($linked, static fn (Builder $query) => $query->where('provider_ticket', $status->ticket->id))
            ->where('provider_namespace', $status->effect->providerNamespace)
            ->where('delivery_key', $status->effect->effectKey);
    }

    /** @return list<object> */
    public function subjectRows(DeliveryClaim $claim): array
    {
        $query = $this->connection->table('ntf_notifications')->where(NotificationScope::logical($claim->scope))->whereNull('cancelled_at');

        if ($claim->notificationId !== null) {
            return array_values($query->where('id', $claim->notificationId)->get()->all());
        }

        $members = JsonDocuments::map($claim->message)['notification_ids'] ?? [];

        if (! is_array($members) || $members === [] || count($members) > 100 || count(array_filter($members, is_string(...))) !== count($members)) {
            return [];
        }
        $rows = $query->whereIn('id', $members)->get();

        return $rows->count() === count($members) ? array_values($rows->all()) : [];
    }

    private function persistOutcome(Builder $query, ?string $runId, int $attempts, DeliveryOutcome $outcome): bool
    {
        $state = match ($outcome->kind) {
            DeliveryOutcomeKind::Accepted => 'accepted',
            DeliveryOutcomeKind::Completed => 'completed',
            DeliveryOutcomeKind::Retryable => $attempts >= $this->maxAttempts ? 'exhausted' : 'retry-scheduled',
            DeliveryOutcomeKind::Permanent => 'permanently-failed',
            DeliveryOutcomeKind::Unknown => 'unknown',
        };
        $next = $state === 'retry-scheduled' && $outcome->retryAfter !== null
            ? $this->clock->now()->add(new DateInterval('PT'.$outcome->retryAfter.'S')) : null;

        return $this->finish($query, $runId, $state, [
            'provider_ref' => $outcome->providerRef,
            'error' => json_encode(['reason' => $outcome->reason], JSON_THROW_ON_ERROR),
            'sent_at' => in_array($state, ['accepted', 'completed'], true) ? $this->clock->now() : null,
            'next_attempt' => $next,
        ]);
    }

    /** @param array<string, mixed> $values */
    private function finish(Builder $query, ?string $runId, string $next, array $values): bool
    {
        $changed = $query->update($values + [
            'state' => $next, 'next_attempt' => null, 'claim_token' => null, 'lease_until' => null,
        ]) === 1;

        if ($changed && $runId !== null) {
            $runStatus = match ($next) {
                'accepted', 'completed' => 'sent',
                'skipped', 'permanently-failed', 'exhausted' => 'skipped',
                default => null,
            };

            if ($runStatus !== null) {
                $this->connection->table('ntf_digest_runs')->where('id', $runId)
                    ->where('state', 'admitted')->update(['state' => $runStatus]);
            }
        }

        return $changed;
    }

    private function sweepScope(): DataScope
    {
        $scope = $this->scopes->forCurrentPrincipal($this->tenants->current());
        $this->current->assert($scope, $this->connection);

        return $scope;
    }

    private function lane(DataScope $scope): Builder
    {
        return $this->connection->table('ntf_deliveries')->where(NotificationScope::logical($scope))->where(NotificationScope::fence($scope));
    }

    private function runnable(Builder $query): Builder
    {
        return $query->whereIn('state', ['pending', 'retry-scheduled'])->where('attempts', '<', $this->maxAttempts)
            ->where('next_attempt', '<=', $this->clock->now());
    }

    private function owned(DeliveryClaim $claim, string $state): Builder
    {
        return $this->lane($claim->scope)->where('id', $claim->id)->where('state', $state)
            ->where('claim_token', $claim->token)->where('lease_until', '>', $this->clock->now());
    }
}
