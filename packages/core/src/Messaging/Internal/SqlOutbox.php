<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Exceptions\OutboxRefused;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlOutbox implements Outbox
{
    public function __construct(
        private CurrentWorkScope $current,
        private StoredWorkIssuer $issuer,
        private MessageContractRegistry $contracts,
        private ClockInterface $clock,
        private DataScopeCodec $scopes,
        private TraceContextBridge $traces = new NullTraceContextBridge,
        private string $table = 'sys_outbox_messages',
    ) {}

    #[Override]
    public function append(ConnectionInterface $connection, MessageEnvelope $message): void
    {
        if ($connection->transactionLevel() < 1) {
            throw OutboxRefused::outsideTransaction($message->contract());
        }

        $this->current->assert($message->scope, $connection);
        $contract = $this->contracts->get(name: $message->name, version: $message->contractVersion);

        if (! $contract->maxPiiClass->permits($message->piiClass)) {
            throw OutboxRefused::piiClassExceeded(
                contract: $contract->id(),
                declared: $contract->maxPiiClass->value,
                actual: $message->piiClass->value,
            );
        }

        $contract->validateFact($message);
        ReferenceWrites::enlist($connection, $message->scope, 'axiomasoft/corex', 'outbox', ['package' => $contract->owner, 'subject' => $message->subjectData(), 'contract' => $contract->id()]);
        $key = [...MessageReceiptIdentity::logical($message->scope), 'source' => $message->source, 'message_id' => $message->messageId];
        $digest = $message->digest();
        $proof = $this->issuer->issue($message->scope, MessageEnvelope::PROOF_OWNER, $digest);
        $now = $this->clock->now();
        $metadata = $this->traces->inject(metadata: $message->metadata);

        JsonCloudEventCodec::assertMetadata($metadata);
        $rows = $connection->table($this->table)->insertOrIgnoreReturning([
            'id' => (string) Str::uuid7(), ...$key, ...MessageReceiptIdentity::fence($message->scope),
            'digest' => $digest, 'scope_seal' => $proof->seal,
            'name' => $message->name,
            'contract_version' => $message->contractVersion,
            'envelope_version' => $message->envelopeVersion,
            'subject' => json_encode($message->subjectData(), JSON_THROW_ON_ERROR),
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'pii_class' => $message->piiClass->value,
            'scope' => json_encode($this->scopes->encode($message->scope), JSON_THROW_ON_ERROR),
            'payload' => $message->payload->encode(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $message->occurredAt->format('Y-m-d H:i:s.uP'),
            'available_at' => $now,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['id'], array_keys($key));

        if ($rows->isEmpty() && $connection->table($this->table)->where($key)->value('digest') !== $digest) {
            throw new OutboxRefused('Conflicting immutable fact for this source and message identity.');
        }
    }
}
