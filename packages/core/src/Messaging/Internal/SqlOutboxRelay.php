<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Data\LeasedMessage;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;
use Throwable;

#[Internal]
final readonly class SqlOutboxRelay implements OutboxRelay
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private DataScopeCodec $scopes,
        private CurrentWorkScope $current,
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

        $rows = $this->connection->transaction(function () use ($leaseOwner, $batchSize, $now, $leaseUntil): array {
            $expired = $this->connection->table('sys_outbox_messages')->where('status', 'publishing')->where('lease_until', '<=', $now)
                ->where('attempts', '>=', $this->maxAttempts)->orderBy('lease_until')->orderBy('id')->limit($batchSize);

            if ($this->connection->getDriverName() === 'pgsql') {
                $expired->lock('for update skip locked');
            }
            $expiredIds = $expired->pluck('id')->all();

            if ($expiredIds !== []) {
                $this->connection->table('sys_outbox_messages')->whereIn('id', $expiredIds)->update(['status' => 'dead',
                    'dead_at' => $now, 'failure_class' => 'lease-expiry-attempts-exhausted', 'claim_token' => null,
                    'lease_owner' => null, 'lease_until' => null, 'updated_at' => $now]);
            }

            $query = $this->connection->table('sys_outbox_messages')
                ->where('attempts', '<', $this->maxAttempts)
                ->where(function (Builder $query) use ($now): void {
                    $query->where(fn (Builder $q) => $q->where('status', 'pending')->where('available_at', '<=', $now))
                        ->orWhere(fn (Builder $q) => $q->where('status', 'publishing')->where('lease_until', '<=', $now));
                })
                ->oldest('available_at')
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
                $row->claim_token = (string) Str::uuid7();
                $this->connection->table('sys_outbox_messages')->where('id', $row->id)->update([
                    'claim_token' => $row->claim_token,
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
                $envelope = $this->envelope($row);

                if ($row->digest !== $envelope->digest() || ! is_string($row->claim_token)) {
                    throw InvalidMessage::identity('stored fact digest');
                }
                foreach ([...MessageReceiptIdentity::logical($envelope->scope), ...MessageReceiptIdentity::fence($envelope->scope)] as $key => $expected) {
                    $actual = $row->{$key};

                    if ((! is_int($actual) && ! is_string($actual)) || (string) $actual !== (string) $expected) {
                        throw InvalidMessage::identity('stored fact scope');
                    }
                }
                $leased[] = new LeasedMessage(
                    envelope: $envelope, rowId: $row->id, claimToken: $row->claim_token,
                    leaseOwner: $leaseOwner,
                    attempt: $attempt,
                    leaseUntil: $leaseUntil,
                );
            } catch (Throwable $error) {
                // The malformed scope cannot authorize work. Fence its restricted source row by the original SQL claim.
                $this->connection->table('sys_outbox_messages')->where([
                    'id' => $row->id, 'claim_token' => $row->claim_token, 'lease_owner' => $leaseOwner, 'attempts' => $attempt,
                    'account_id' => $row->account_id, 'account_incarnation' => $row->account_incarnation, 'workspace_scope' => $row->workspace_scope,
                    'deployment_id' => $row->deployment_id, 'binding_id' => $row->binding_id, 'binding_generation' => $row->binding_generation,
                    'restore_epoch' => $row->restore_epoch, 'status' => 'publishing',
                ])->where('lease_until', '>', $this->clock->now())->update(['status' => 'quarantined', 'claim_token' => null, 'lease_owner' => null, 'lease_until' => null,
                    'failure_class' => 'invalid-fact', 'last_error' => $error::class, 'updated_at' => $this->clock->now()]);
            }
        }

        return $leased;
    }

    private function claimRow(LeasedMessage $message): Builder
    {
        return $this->connection->table('sys_outbox_messages')->where('id', $message->rowId)->where('status', 'publishing')
            ->where('lease_owner', $message->leaseOwner)->where('claim_token', $message->claimToken)
            ->where('lease_until', '>', $this->clock->now())->where('attempts', $message->attempt)
            ->where(MessageReceiptIdentity::logical($message->envelope->scope))->where(MessageReceiptIdentity::fence($message->envelope->scope));
    }

    public function assertClaim(LeasedMessage $message): void
    {
        $this->current->assert(scope: $message->envelope->scope, connection: $this->connection);

        if (! $this->claimRow($message)->exists()) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    public function quarantine(LeasedMessage $message, Throwable $error): void
    {
        if ($this->claimRow($message)->update(['status' => 'quarantined', 'claim_token' => null, 'lease_owner' => null, 'lease_until' => null,
            'failure_class' => 'untrusted-work', 'last_error' => $error::class, 'updated_at' => $this->clock->now()]) !== 1) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    #[Override]
    public function markPublished(LeasedMessage $message): void
    {
        $this->current->assert(scope: $message->envelope->scope, connection: $this->connection);
        $now = $this->clock->now();

        $changed = $this->claimRow($message)
            ->update([
                'status' => 'published',
                'published_at' => $now,
                'lease_owner' => null,
                'claim_token' => null,
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

        $changed = $this->claimRow($message)
            ->update([
                'status' => $dead ? 'dead' : 'pending',
                'available_at' => $dead ? $now : $now->add(new DateInterval("PT{$delay}S")),
                'dead_at' => $dead ? $now : null,
                'lease_owner' => null,
                'claim_token' => null,
                'lease_until' => null,
                'failure_class' => $dead ? 'attempts-exhausted' : 'publish-failed',
                'last_error' => get_debug_type($error),
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw OutboxLeaseLost::superseded($message->envelope->messageId);
        }
    }

    private function envelope(stdClass $row): MessageEnvelope
    {
        assert(is_string($row->message_id));
        assert(is_string($row->scope_seal));
        assert(is_string($row->source));
        assert(is_string($row->name));
        assert(is_string($row->scope));
        assert(is_string($row->metadata));
        assert(is_string($row->payload));
        assert(is_string($row->pii_class));
        assert(is_int($row->contract_version) || is_numeric($row->contract_version));
        assert(is_int($row->envelope_version) || is_numeric($row->envelope_version));
        assert(is_string($row->subject));
        assert(is_string($row->correlation_id));
        assert($row->causation_id === null || is_string($row->causation_id));
        /** @var array<string, scalar|null> $scope */
        $scope = json_decode($row->scope, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, scalar|null> $metadata */
        $metadata = json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR);
        JsonCloudEventCodec::assertMetadata($metadata);

        return new MessageEnvelope(
            messageId: $row->message_id,
            source: $row->source,
            name: $row->name,
            contractVersion: (int) $row->contract_version,
            occurredAt: $this->date($row->occurred_at),
            scope: $this->scopes->decode($scope),
            payload: MessagePayload::fromJson($row->payload),
            piiClass: PiiClass::from($row->pii_class),
            subject: JsonCloudEventCodec::subject($row->subject),
            correlationId: $row->correlation_id, seal: $row->scope_seal,
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
