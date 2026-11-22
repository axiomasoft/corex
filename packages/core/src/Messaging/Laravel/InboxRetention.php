<?php

declare(strict_types=1);

namespace CoreX\Messaging\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Internal\MessageReceiptIdentity;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Explicit consumer-owned cleanup; inbox marks are otherwise retained indefinitely. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class InboxRetention
{
    public function __construct(
        private ConnectionInterface $connection,
        private CurrentWorkScope $current,
        private string $table = 'sys_inbox_messages',
    ) {}

    /**
     * The consumer must establish that these messages cannot be replayed before forgetting them.
     *
     * @param  list<string>  $messageIds
     */
    public function forget(DataScope $scope, ConsumerId $consumer, string $source, array $messageIds): int
    {
        if ($messageIds === []) {
            return 0;
        }

        return $this->connection->transaction(function () use ($scope, $consumer, $source, $messageIds): int {
            $this->current->assert(scope: $scope, connection: $this->connection);

            return $this->connection->table($this->table)->where(MessageReceiptIdentity::logical($scope))
                ->where('source', $source)->where('consumer', $consumer->name)
                ->whereIn('message_id', $messageIds)
                ->delete();
        });
    }
}
