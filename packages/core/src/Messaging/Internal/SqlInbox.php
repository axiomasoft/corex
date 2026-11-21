<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Exceptions\InboxRefused;
use Illuminate\Database\ConnectionInterface;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlInbox implements Inbox
{
    public function __construct(
        private CurrentWorkScope $current,
        private MessageContractRegistry $contracts,
        private ClockInterface $clock,
        private string $table = 'sys_inbox_messages',
    ) {}

    #[Override]
    public function claim(ConnectionInterface $connection, ConsumerId $consumer, MessageEnvelope $message): bool
    {
        if ($connection->transactionLevel() < 1) {
            throw InboxRefused::outsideTransaction($consumer->name);
        }
        $this->current->assert($message->scope, $connection);
        $contract = $this->contracts->get($message->name, $message->contractVersion);

        if (! $contract->maxPiiClass->permits($message->piiClass)) {
            throw new InboxRefused('Message exceeds the registered PII class.');
        }
        $contract->validateFact($message);
        ReferenceWrites::enlist($connection, $message->scope, 'axiomasoft/corex', 'inbox', ['package' => $contract->owner, 'subject' => $message->subjectData(), 'consumer' => $consumer->name]);
        $key = [...MessageReceiptIdentity::logical($message->scope), 'consumer' => $consumer->name, 'source' => $message->source, 'message_id' => $message->messageId];
        $digest = $message->digest();
        $inserted = $connection->table($this->table)->insertOrIgnoreReturning([
            ...$key, ...MessageReceiptIdentity::fence($message->scope), 'name' => $message->name,
            'contract_version' => $message->contractVersion, 'consumer_version' => $consumer->version,
            'digest' => $digest, 'processed_at' => $this->clock->now(),
        ], ['message_id'], array_keys($key));

        if ($inserted->isNotEmpty()) {
            return true;
        }

        if ($connection->table($this->table)->where($key)->value('digest') !== $digest) {
            throw new InboxRefused('Conflicting fact for this logical consumer receipt.');
        }

        return false;
    }
}
