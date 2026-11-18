<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Exceptions\OutboxRefused;
use Illuminate\Database\ConnectionInterface;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlOutbox implements Outbox
{
    public function __construct(
        private ConnectionInterface $connection,
        private MessageContractRegistry $contracts,
        private ClockInterface $clock,
        private TraceContextBridge $traces = new NullTraceContextBridge,
        private string $table = 'sys_outbox_messages',
    ) {}

    #[Override]
    public function append(MessageEnvelope $message): void
    {
        if ($this->connection->transactionLevel() < 1) {
            throw OutboxRefused::outsideTransaction($message->contract());
        }

        $contract = $this->contracts->get(name: $message->name, version: $message->contractVersion);

        if (! $contract->maxPiiClass->permits($message->piiClass)) {
            throw OutboxRefused::piiClassExceeded(
                contract: $contract->id(),
                declared: $contract->maxPiiClass->value,
                actual: $message->piiClass->value,
            );
        }

        $now = $this->clock->now();
        $metadata = $this->traces->inject(metadata: $message->metadata);

        $this->connection->table($this->table)->insert([
            'id' => $message->messageId,
            'name' => $message->name,
            'contract_version' => $message->contractVersion,
            'envelope_version' => $message->envelopeVersion,
            'source' => $message->source,
            'subject' => $message->subject,
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'pii_class' => $message->piiClass->value,
            'scope' => json_encode($message->scope, JSON_THROW_ON_ERROR),
            'payload' => $message->payload->encode(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $message->occurredAt,
            'available_at' => $now,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
