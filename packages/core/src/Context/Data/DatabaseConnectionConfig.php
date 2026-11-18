<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use Packman\Attributes\Api;

/**
 * Verified database identity projected by the host's account resource owner.
 * Contains connection metadata, not credentials or routing behavior.
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
