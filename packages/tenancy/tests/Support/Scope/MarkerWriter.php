<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Scope;

use Illuminate\Database\ConnectionInterface;

/** A scoped service that captures the connection it was resolved with (the 60-service shape). */
final readonly class MarkerWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function write(string $marker): void
    {
        $this->connection->table('scope_markers')->insert(['marker' => $marker]);
    }

    public function database(): string
    {
        return $this->connection->getDatabaseName();
    }
}
