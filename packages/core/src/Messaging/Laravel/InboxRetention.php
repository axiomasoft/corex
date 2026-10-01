<?php

declare(strict_types=1);

namespace CoreX\Messaging\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Database\ConnectionInterface;

/** Explicit consumer-owned cleanup; inbox marks are otherwise retained indefinitely. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class InboxRetention
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'sys_inbox_messages',
    ) {}

    /**
     * The consumer must establish that these messages cannot be replayed before forgetting them.
     *
     * @param  list<string>  $messageIds
     */
    public function forget(string $consumer, array $messageIds): int
    {
        if ($messageIds === []) {
            return 0;
        }

        return $this->connection->table($this->table)
            ->where('consumer', $consumer)
            ->whereIn('message_id', $messageIds)
            ->delete();
    }
}
