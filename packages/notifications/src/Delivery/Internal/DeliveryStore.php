<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\DeliveryClaim;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use Illuminate\Database\ConnectionInterface;
use JsonException;

final class DeliveryStore
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly int $claimSeconds,
        private readonly int $maxAttempts,
    ) {}

    /** @return list<string> */
    public function dueIds(int $limit = 100): array
    {
        return $this->connection->table('ntf_deliveries')
            ->whereIn('state', ['pending', 'failed'])
            ->where(static fn ($query) => $query->whereNull('next_attempt')->orWhere('next_attempt', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    public function recoverExpired(): int
    {
        return $this->connection->table('ntf_deliveries')
            ->where('state', 'claimed')
            ->where('lease_until', '<=', now())
            ->update([
                'state' => 'unknown',
                'lease_until' => null,
                'next_attempt' => null,
            ]);
    }

    public function claim(string $deliveryId): ?DeliveryClaim
    {
        return $this->connection->transaction(function () use ($deliveryId): ?DeliveryClaim {
            $delivery = $this->connection->table('ntf_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();

            if ($delivery === null
                || ! in_array($delivery->state, ['pending', 'failed'], true)
                || (int) $delivery->attempts >= $this->maxAttempts
                || ($delivery->next_attempt !== null && $delivery->next_attempt > now())) {
                return null;
            }

            $epoch = (int) $delivery->claim_epoch + 1;
            $attempts = (int) $delivery->attempts + 1;
            $this->connection->table('ntf_deliveries')->where('id', $deliveryId)->update([
                'state' => 'claimed',
                'claim_epoch' => (string) $epoch,
                'attempts' => $attempts,
                'lease_until' => now()->addSeconds($this->claimSeconds),
            ]);

            return new DeliveryClaim(
                id: $delivery->id,
                notificationId: $delivery->notification_id,
                digestRunId: $delivery->digest_run_id,
                channel: $delivery->channel,
                deliveryKey: $delivery->delivery_key,
                epoch: $epoch,
                attempts: $attempts,
                message: JsonDocuments::capture($this->message($delivery->message)),
                messageHash: $delivery->message_hash,
                templateVersion: $delivery->template_version,
                policyVersion: $delivery->policy_version,
                driverVersion: $delivery->driver_version,
            );
        });
    }

    public function complete(DeliveryClaim $claim, DeliveryOutcome $outcome): void
    {
        $this->connection->transaction(function () use ($claim, $outcome): void {
            $delivery = $this->connection->table('ntf_deliveries')->where('id', $claim->id)->lockForUpdate()->first();

            if ($delivery === null || $delivery->state !== 'claimed' || (int) $delivery->claim_epoch !== $claim->epoch) {
                return;
            }

            $state = match ($outcome->kind) {
                'accepted' => 'sent',
                'retryable' => (int) $delivery->attempts >= $this->maxAttempts ? 'failed' : 'failed',
                'permanent' => 'failed',
                'unknown' => 'unknown',
                default => throw new NotificationOperationConflict('Notification driver returned an unsupported outcome.'),
            };
            $retryAfter = $outcome->kind === 'retryable' ? $this->retryAfter($outcome->retryAfter) : null;

            $updated = $this->connection->table('ntf_deliveries')->where('id', $claim->id)->update([
                'state' => $state,
                'provider_ref' => $outcome->providerRef,
                'error' => $outcome->kind === 'accepted' ? null : json_encode(['reason' => $outcome->reason], JSON_THROW_ON_ERROR),
                'sent_at' => $outcome->kind === 'accepted' ? now() : null,
                'next_attempt' => $retryAfter === null ? null : now()->addSeconds($retryAfter),
                'lease_until' => null,
            ]);

            if ($updated === 1 && $claim->digestRunId !== null && $outcome->kind === 'accepted') {
                $this->connection->table('ntf_digest_runs')
                    ->where('id', $claim->digestRunId)
                    ->where('state', 'admitted')
                    ->update(['state' => 'sent']);
            }
        });
    }

    public function skip(DeliveryClaim $claim, string $reason): void
    {
        $this->finish($claim, state: 'skipped', error: ['reason' => $reason]);
    }

    public function markUnknown(DeliveryClaim $claim, string $reason): void
    {
        $this->finish($claim, state: 'unknown', error: ['reason' => $reason]);
    }

    private function retryAfter(?int $retryAfter): int
    {
        if ($retryAfter === null || $retryAfter < 1 || $retryAfter > 300) {
            throw new NotificationOperationConflict('Notification retry delay must be between one and 300 seconds.');
        }

        return $retryAfter;
    }

    /** @param array<string, string> $error */
    private function finish(DeliveryClaim $claim, string $state, array $error): void
    {
        $updated = $this->connection->table('ntf_deliveries')
            ->where('id', $claim->id)
            ->where('state', 'claimed')
            ->where('claim_epoch', (string) $claim->epoch)
            ->update([
                'state' => $state,
                'error' => json_encode($error, JSON_THROW_ON_ERROR),
                'lease_until' => null,
                'next_attempt' => null,
            ]);

        if ($updated === 1 && $claim->digestRunId !== null) {
            $this->connection->table('ntf_digest_runs')
                ->where('id', $claim->digestRunId)
                ->where('state', 'admitted')
                ->update(['state' => 'skipped']);
        }
    }

    /** @return array<string, mixed> */
    private function message(?string $message): array
    {
        if ($message === null) {
            throw new NotificationOperationConflict('Notification delivery has no admitted message.');
        }

        try {
            $decoded = json_decode($message, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NotificationOperationConflict('Notification delivery message is invalid.', previous: $exception);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new NotificationOperationConflict('Notification delivery message must be an object.');
        }

        return $decoded;
    }
}
