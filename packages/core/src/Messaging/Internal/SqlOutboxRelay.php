<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Data\LeasedMessage;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Override;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class SqlOutboxRelay implements OutboxRelay
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private int $leaseSeconds,
        private int $maxAttempts,
        private int $backoffCapSeconds,
    ) {}

    /** @return list<LeasedMessage> */
    #[Override]
    public function lease(string $leaseOwner, int $batchSize): array
    {
        $now = $this->clock->now();
        $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
        $table = app(CoreOptions::class)->outboxMessagesTable;

        /** @var list<object> $rows */
        $rows = $this->connection->transaction(function () use ($table, $leaseOwner, $batchSize, $now, $leaseUntil): array {
            $this->connection->table($table)
                ->where('status', 'publishing')
                ->where('lease_until', '<=', $now)
                ->where('attempts', '>=', $this->maxAttempts)
                ->update([
                    'status' => 'dead',
                    'dead_at' => $now,
                    'failure_class' => 'lease-expiry-attempts-exhausted',
                    'lease_owner' => null,
                    'lease_until' => null,
                    'updated_at' => $now,
                ]);

            $this->connection->table($table)
                ->where('status', 'publishing')
                ->where('lease_until', '<=', $now)
                ->where('attempts', '<', $this->maxAttempts)
                ->update([
                    'status' => 'pending',
                    'lease_owner' => null,
                    'lease_until' => null,
                    'updated_at' => $now,
                ]);

            $query = $this->connection->table($table)
                ->where('status', 'pending')
                ->where('available_at', '<=', $now)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->limit($batchSize);

            if ($this->connection->getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $claimed = $query->get()->all();

            foreach ($claimed as $row) {
                $this->connection->table($table)->where('id', $row->id)->update([
                    'status' => 'publishing',
                    'lease_owner' => $leaseOwner,
                    'lease_until' => $leaseUntil,
                    'attempts' => (int) $row->attempts + 1,
                    'updated_at' => $now,
                ]);
            }

            return array_values($claimed);
        });

        $leased = [];

        foreach ($rows as $row) {
            $attempt = (int) $row->attempts + 1;

            try {
                $leased[] = new LeasedMessage(
                    envelope: $this->envelope($row),
                    leaseOwner: $leaseOwner,
                    attempt: $attempt,
                    leaseUntil: $leaseUntil,
                );
            } catch (Throwable $error) {
                $this->releaseWithBackoff(
                    message: $this->syntheticLeasedMessage(
                        messageId: (string) $row->id,
                        leaseOwner: $leaseOwner,
                        attempt: $attempt,
                        leaseUntil: $leaseUntil,
                    ),
                    error: $error,
                );
            }
        }

        return $leased;
    }

    #[Override]
    public function markPublished(LeasedMessage $message): void
    {
        $now = $this->clock->now();

        $changed = $this->connection->table(app(CoreOptions::class)->outboxMessagesTable)
            ->where('id', $message->envelope->messageId)
            ->where('status', 'publishing')
            ->where('lease_owner', $message->leaseOwner)
            ->update([
                'status' => 'published',
                'published_at' => $now,
                'lease_owner' => null,
                'lease_until' => null,
                'failure_class' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    #[Override]
    public function releaseWithBackoff(LeasedMessage $message, Throwable $error): void
    {
        $now = $this->clock->now();
        $dead = $message->attempt >= $this->maxAttempts;
        $delay = min($this->backoffCapSeconds, 2 ** $message->attempt);

        $changed = $this->connection->table(app(CoreOptions::class)->outboxMessagesTable)
            ->where('id', $message->envelope->messageId)
            ->where('status', 'publishing')
            ->where('lease_owner', $message->leaseOwner)
            ->update([
                'status' => $dead ? 'dead' : 'pending',
                'available_at' => $dead ? $now : $now->add(new DateInterval("PT{$delay}S")),
                'dead_at' => $dead ? $now : null,
                'lease_owner' => null,
                'lease_until' => null,
                'failure_class' => $dead ? 'attempts-exhausted' : 'publish-failed',
                'last_error' => mb_substr($error->getMessage(), 0, 2_000),
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    private function syntheticLeasedMessage(
        string $messageId,
        string $leaseOwner,
        int $attempt,
        DateTimeImmutable $leaseUntil,
    ): LeasedMessage {
        return new LeasedMessage(
            envelope: new MessageEnvelope(
                messageId: $messageId,
                source: 'corex/relay',
                name: 'corrupt.row',
                contractVersion: 1,
                occurredAt: $this->clock->now(),
                scope: ['accountId' => 'unknown'],
                payload: MessagePayload::empty(),
                piiClass: PiiClass::None,
            ),
            leaseOwner: $leaseOwner,
            attempt: $attempt,
            leaseUntil: $leaseUntil,
        );
    }

    private function envelope(object $row): MessageEnvelope
    {
        /** @var array<string, scalar|null> $scope */
        $scope = json_decode((string) $row->scope, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, scalar|null> $metadata */
        $metadata = json_decode((string) $row->metadata, true, flags: JSON_THROW_ON_ERROR);

        return new MessageEnvelope(
            messageId: (string) $row->id,
            source: (string) $row->source,
            name: (string) $row->name,
            contractVersion: (int) $row->contract_version,
            occurredAt: $this->date($row->occurred_at),
            scope: $scope,
            payload: MessagePayload::fromJson((string) $row->payload),
            piiClass: PiiClass::from((string) $row->pii_class),
            subject: $row->subject === null ? null : (string) $row->subject,
            correlationId: $row->correlation_id === null ? null : (string) $row->correlation_id,
            causationId: $row->causation_id === null ? null : (string) $row->causation_id,
            metadata: $metadata,
            envelopeVersion: (int) $row->envelope_version,
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);
    }
}
