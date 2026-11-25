<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use Illuminate\Database\Connection;
use Packman\Attributes\Internal;

/**
 * Remembers, for one request or job, which tables were seen to exist.
 *
 * Bound as a scoped instance, so the container drops it between Octane
 * requests and queue jobs. Only a positive answer is kept: a table that a
 * module migration creates later in the same request is still found by the
 * next probe. The key is the physical database behind the connection, so a
 * tenant switch that repoints a connection name never reuses another
 * database's answer.
 */
#[Internal]
final class ExistingTables
{
    /** @var array<string, true> */
    private array $seen = [];

    public function has(Connection $connection, string $table): bool
    {
        $key = $this->key($connection, $table);

        if (isset($this->seen[$key])) {
            return true;
        }

        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return false;
        }
        $this->seen[$key] = true;

        return true;
    }

    private function key(Connection $connection, string $table): string
    {
        return json_encode([
            $connection->getName(),
            $connection->getDriverName(),
            $connection->getConfig('host'),
            $connection->getConfig('port'),
            $connection->getDatabaseName(),
            $connection->getConfig('search_path'),
            $connection->getTablePrefix(),
            $table,
        ], JSON_THROW_ON_ERROR);
    }
}
