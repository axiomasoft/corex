<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Exceptions\InboxRefused;
use Illuminate\Database\ConnectionInterface;
use Override;
use Psr\Clock\ClockInterface;

final readonly class SqlInbox implements Inbox
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function claim(string $consumer, string $messageId, string $name): bool
    {
        if ($this->connection->transactionLevel() < 1) {
            throw InboxRefused::outsideTransaction($consumer);
        }

        // insertOrIgnore компилируется в INSERT ... ON CONFLICT DO NOTHING на pgsql.
        // Предварительный SELECT здесь был бы гонкой, а не защитой (P5-Q2).
        $inserted = $this->connection->table(app(CoreOptions::class)->inboxMessagesTable)->insertOrIgnore([
            'consumer' => $consumer,
            'message_id' => $messageId,
            'name' => $name,
            'processed_at' => $this->clock->now(),
        ]);

        return $inserted === 1;
    }
}
