<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Attributes\Api;

/**
 * Full Laravel connection config for an account's tenant database, resolved
 * from root_clusters+db_name.
 */
#[Api(since: '1.0')]
final readonly class DatabaseConnectionConfig
{
    /**
     * @param  array<string, scalar|null>  $options
     */
    public function __construct(
        public string $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public array $options = [],
    ) {}
}
