<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Internal;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionRecord;
use Packman\Attributes\Internal;
use WeakMap;

/**
 * Principal admissions already proven under a shared lock in the open transaction.
 *
 * A locked admission holds share locks on the user, workspace and membership
 * rows until the transaction (or the savepoint that took them) ends, so the
 * same rows cannot change under it and a second read in that transaction
 * returns the same answer. Entries hang off Laravel's transaction records:
 * - a savepoint rolled back drops its record, and with it the proof, because
 *   its locks were released;
 * - a commit or rollback of the outer transaction drops every record;
 * - the binding is scoped, so the container forgets it between Octane
 *   requests and queue jobs; nothing is static.
 * Without a transaction manager on the connection nothing is remembered and
 * every check reads the rows again.
 */
#[Internal]
final class LockedAdmissions
{
    /** @var WeakMap<DatabaseTransactionRecord, array<string, true>> */
    private WeakMap $proofs;

    public function __construct(private readonly Container $container)
    {
        $this->proofs = new WeakMap;
    }

    public function proven(Connection $connection, string $key): bool
    {
        return array_any($this->open($connection), fn (DatabaseTransactionRecord $record): bool => isset($this->proofs[$record][$key]));
    }

    public function remember(Connection $connection, string $key): void
    {
        $records = $this->open($connection);
        $current = end($records);

        if ($current === false || $current->level !== $connection->transactionLevel()) {
            return;
        }
        $this->proofs[$current] = [...($this->proofs[$current] ?? []), $key => true];
    }

    /** @return list<DatabaseTransactionRecord> */
    private function open(Connection $connection): array
    {
        $level = $connection->transactionLevel();

        if ($level === 0 || ! $this->container->bound('db.transactions')) {
            return [];
        }
        return array_values($this->container->make('db.transactions')->getPendingTransactions()
            ->filter(static fn (DatabaseTransactionRecord $record): bool => $record->connection === $connection->getName() && $record->level <= $level)
            ->sortBy('level')
            ->all());
    }
}
