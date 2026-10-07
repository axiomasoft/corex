<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

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
use stdClass;
use Throwable;

final readonly class SqlOutboxRelay implements OutboxRelay
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private int $leaseSeconds,
        private int $maxAttempts,
        private int $backoffCapSeconds,
        private string $table = 'sys_outbox_messages',
    ) {}

    /** @return list<LeasedMessage> */
    #[Override]
    public function lease(string $leaseOwner, int $batchSize): array
    {
        $now = $this->clock->now();
        $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
        $table = $this->table;

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
                ->oldest('occurred_at')
                ->orderBy('id')
                ->limit($batchSize);

            if ($this->connection->getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $claimed = $query->get()->all();

            foreach ($claimed as $row) {
                assert(is_int($row->attempts) || is_numeric($row->attempts));
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
            assert(is_int($row->attempts) || is_numeric($row->attempts));
            assert(is_string($row->id));
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
                        messageId: $row->id,
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

        $changed = $this->connection->table($this->table)
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

        $changed = $this->connection->table($this->table)
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

    private function envelope(stdClass $row): MessageEnvelope
    {
        assert(is_string($row->id));
        assert(is_string($row->source));
        assert(is_string($row->name));
        assert(is_string($row->scope));
        assert(is_string($row->metadata));
        assert(is_string($row->payload));
        assert(is_string($row->pii_class));
        assert(is_int($row->contract_version) || is_numeric($row->contract_version));
        assert(is_int($row->envelope_version) || is_numeric($row->envelope_version));
        assert($row->subject === null || is_string($row->subject));
        assert($row->correlation_id === null || is_string($row->correlation_id));
        assert($row->causation_id === null || is_string($row->causation_id));
        /** @var array<string, scalar|null> $scope */
        $scope = json_decode($row->scope, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, scalar|null> $metadata */
        $metadata = json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR);

        return new MessageEnvelope(
            messageId: $row->id,
            source: $row->source,
            name: $row->name,
            contractVersion: (int) $row->contract_version,
            occurredAt: $this->date($row->occurred_at),
            scope: $scope,
            payload: MessagePayload::fromJson($row->payload),
            piiClass: PiiClass::from($row->pii_class),
            subject: $row->subject ?? null,
            correlationId: $row->correlation_id ?? null,
            causationId: $row->causation_id ?? null,
            metadata: $metadata,
            envelopeVersion: (int) $row->envelope_version,
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        assert(is_scalar($value) || $value === null || $value instanceof DateTimeInterface);

        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);
    }
}
